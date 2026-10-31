<?php

namespace App\Services;

use App\Data\OutboxLease;
use App\Enums\ErrorCode;
use App\Enums\OutboxAggregateType;
use App\Enums\OutboxFailureReason;
use App\Enums\OutboxStatus;
use App\Exceptions\DomainException;
use App\Models\AfterSalesRequest;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Refund;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\CanonicalPayload;
use App\Support\OperationKey;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class OutboxService
{
    public const MAX_ATTEMPTS = 10;

    public const DEFAULT_LEASE_SECONDS = 120;

    public const MAX_PAYLOAD_BYTES = 65536;

    public function __construct(private readonly BusinessClock $clock) {}

    /** @param array<array-key, mixed> $payload */
    public function enqueue(string $dedupKey, string $eventType, OutboxAggregateType $aggregateType, int $aggregateId, array $payload, DateTimeInterface|string|null $availableAt = null): OutboxEvent
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Outbox enqueue requires the surrounding business transaction.');
        }
        OperationKey::validate($dedupKey);
        if (! preg_match('/^[A-Z][A-Z0-9_]{0,79}$/D', $eventType) || $aggregateId < 1) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        $this->validatePayload($payload);
        $availableAt = $availableAt === null ? $this->clock->now() : $this->clock->instant($availableAt);
        try {
            return DB::transaction(function () use ($dedupKey, $eventType, $aggregateType, $aggregateId, $payload, $availableAt): OutboxEvent {
                if ($existing = OutboxEvent::where('dedup_key', $dedupKey)->first()) {
                    return $this->replay($existing, $dedupKey, $eventType, $aggregateType, $aggregateId, $payload);
                }
                $exists = match ($aggregateType) {
                    OutboxAggregateType::Order => Order::whereKey($aggregateId)->exists(),
                    OutboxAggregateType::Rma => AfterSalesRequest::whereKey($aggregateId)->exists(),
                    OutboxAggregateType::Refund => Refund::whereKey($aggregateId)->exists(),
                    OutboxAggregateType::User => User::withTrashed()->whereKey($aggregateId)->exists(),
                };
                if (! $exists) {
                    throw new DomainException(ErrorCode::NotFound);
                }
                $event = new OutboxEvent;
                $event->dedup_key = $dedupKey;
                $event->event_type = $eventType;
                $event->aggregate_type = $aggregateType;
                $event->aggregate_id = $aggregateId;
                $event->payload = $payload;
                $event->status = OutboxStatus::Pending;
                $event->attempts = 0;
                $event->available_at = $availableAt;
                $event->created_at = $this->clock->now();
                $event->updated_at = $this->clock->now();
                $event->save();

                return $event->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = OutboxEvent::where('dedup_key', $dedupKey)->lockForUpdate()->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $dedupKey, $eventType, $aggregateType, $aggregateId, $payload);
        }
    }

    public function claim(int $eventId, int $leaseSeconds = self::DEFAULT_LEASE_SECONDS): ?OutboxLease
    {
        $this->requireCommittedWorker();
        if ($eventId < 1 || $leaseSeconds < 1 || $leaseSeconds > 3600) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }

        return DB::transaction(function () use ($eventId, $leaseSeconds): ?OutboxLease {
            $event = OutboxEvent::whereKey($eventId)->lockForUpdate()->first();
            $now = $this->clock->now();
            if ($event === null || $event->status === OutboxStatus::Sent || $event->available_at->greaterThan($now)
                || ($event->status === OutboxStatus::Processing && $event->lease_until !== null && $now->lessThan($event->lease_until))
                || in_array($event->last_error, [OutboxFailureReason::AttemptsExhausted->value, OutboxFailureReason::InvalidPayload->value], true)) {
                return null;
            }
            if ($event->attempts >= self::MAX_ATTEMPTS) {
                $event->status = OutboxStatus::Failed;
                $event->lease_until = null;
                $event->last_error = OutboxFailureReason::AttemptsExhausted->value;
                $event->updated_at = $now;
                $event->save();

                return null;
            }
            $event->attempts++;
            try {
                if (! is_array($event->payload)) {
                    throw new DomainException(ErrorCode::InvalidPayload);
                }
                $this->validatePayload($event->payload);
            } catch (DomainException) {
                $event->status = OutboxStatus::Failed;
                $event->lease_until = null;
                $event->last_error = OutboxFailureReason::InvalidPayload->value;
                $event->updated_at = $now;
                $event->save();

                return null;
            }
            $event->status = OutboxStatus::Processing;
            $event->lease_until = $now->addSeconds($leaseSeconds);
            $event->last_error = null;
            $event->updated_at = $now;
            $event->save();

            return new OutboxLease($event->id, $event->attempts, $event->lease_until, $event->event_type, $event->aggregate_type, $event->aggregate_id, $event->dedup_key, $event->payload);
        }, 3);
    }

    public function sent(OutboxLease $lease): bool
    {
        $this->requireCommittedWorker();

        return $this->ownedLease($lease)->update([
            'status' => OutboxStatus::Sent->value, 'sent_at' => $this->clock->now()->format('Y-m-d H:i:s.u'),
            'lease_until' => null, 'last_error' => null, 'updated_at' => $this->clock->now()->format('Y-m-d H:i:s.u'),
        ]) === 1;
    }

    public function failed(OutboxLease $lease, OutboxFailureReason $reason = OutboxFailureReason::DeliveryFailed): bool
    {
        $this->requireCommittedWorker();
        $reason = $lease->attempt >= self::MAX_ATTEMPTS ? OutboxFailureReason::AttemptsExhausted : $reason;
        $backoff = min(3600, 30 * (2 ** min(9, max(0, $lease->attempt - 1))));

        return $this->ownedLease($lease)->update([
            'status' => OutboxStatus::Failed->value, 'lease_until' => null, 'last_error' => $reason->value,
            'available_at' => $this->clock->now()->addSeconds($backoff)->format('Y-m-d H:i:s.u'),
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s.u'),
        ]) === 1;
    }

    private function ownedLease(OutboxLease $lease): Builder
    {
        return OutboxEvent::whereKey($lease->eventId)->where('status', OutboxStatus::Processing->value)
            ->where('attempts', $lease->attempt)->where('lease_until', $lease->leaseUntil->utc()->format('Y-m-d H:i:s.u'))
            ->where('lease_until', '>', $this->clock->now()->format('Y-m-d H:i:s.u'));
    }

    private function requireCommittedWorker(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Outbox delivery and acknowledgements must run after the business commit.');
        }
    }

    /** @param array<array-key, mixed> $payload */
    private function replay(OutboxEvent $event, string $key, string $type, OutboxAggregateType $aggregate, int $id, array $payload): OutboxEvent
    {
        if ($event->dedup_key !== $key || $event->event_type !== $type || $event->aggregate_type !== $aggregate || $event->aggregate_id !== $id
            || CanonicalPayload::fingerprint($event->payload) !== CanonicalPayload::fingerprint($payload)) {
            throw new DomainException(ErrorCode::IdempotencyMismatch);
        }

        return $event;
    }

    /** @param array<array-key, mixed> $payload */
    private function validatePayload(array $payload): void
    {
        $this->checkPayloadValue($payload);
        CanonicalPayload::fingerprint($payload);
        if (strlen(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > self::MAX_PAYLOAD_BYTES) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
    }

    private function checkPayloadValue(mixed $value, int $depth = 0): void
    {
        if ($depth > 32) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $normalized = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string) $key));
                if (in_array($normalized, ['password', 'passwordconfirmation', 'currentpassword', 'otp', 'otpcode', 'token', 'secret', 'sessionid', 'bankaccount', 'accountnumber', 'destinationciphertext', 'hashsecret'], true)) {
                    throw new DomainException(ErrorCode::InvalidPayload);
                }
                $this->checkPayloadValue($child, $depth + 1);
            }
        } elseif ($value !== null && ! is_string($value) && ! is_int($value) && ! is_bool($value)) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
    }
}
