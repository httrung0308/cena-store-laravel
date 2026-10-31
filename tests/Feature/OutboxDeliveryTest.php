<?php

namespace Tests\Feature;

use App\Contracts\OutboxDelivery;
use App\Data\OutboxLease;
use App\Enums\OutboxAggregateType;
use App\Enums\OutboxFailureReason;
use App\Enums\OutboxStatus;
use App\Enums\SalesLedgerType;
use App\Jobs\ProcessOutbox;
use App\Services\OutboxDeliveryRegistry;
use App\Services\OutboxService;
use App\Services\SalesLedgerService;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class OutboxDeliveryTest extends CommittedAtomicTestCase
{
    public function test_producers_require_a_business_transaction_and_delivery_cannot_run_before_commit(): void
    {
        $order = $this->order();
        $outbox = app(OutboxService::class);
        foreach ([fn () => $outbox->enqueue($this->namespace.'no-tx', 'USER_UPDATED', OutboxAggregateType::User, $this->actor->id, []), fn () => app(SalesLedgerService::class)->postUnique($this->namespace.'ledger', SalesLedgerType::ShippingRecognized, [], ['order_id' => $order->id], '2026-10-31T03:00:00Z')] as $call) {
            try {
                $call();
                $this->fail('Producer ran without a transaction.');
            } catch (LogicException $exception) {
                $this->assertSame(LogicException::class, $exception::class);
            }
        }
        DB::beginTransaction();
        try {
            $event = $outbox->enqueue($this->namespace.'before-commit', 'USER_UPDATED', OutboxAggregateType::User, $this->actor->id, []);
            $this->expectException(LogicException::class);
            $outbox->claim($event->id);
        } finally {
            DB::rollBack();
        }
    }

    public function test_only_one_live_lease_exists_and_available_time_is_inclusive(): void
    {
        $clock = app(BusinessClock::class);
        $clock->freeze('2026-10-31T03:00:00Z');
        $event = DB::transaction(fn () => app(OutboxService::class)->enqueue($this->namespace.'future', 'USER_UPDATED', OutboxAggregateType::User, $this->actor->id, [], '2026-10-31T03:00:01Z'));
        $outbox = app(OutboxService::class);
        $this->assertNull($outbox->claim($event->id));
        $clock->freeze('2026-10-31T03:00:01Z');
        $lease = $outbox->claim($event->id);
        $this->assertNotNull($lease);
        $this->assertSame(1, $lease->attempt);
        $this->assertSame('2026-10-31 03:02:01', $lease->leaseUntil->format('Y-m-d H:i:s'));
        $this->assertNull($outbox->claim($event->id));
    }

    public function test_exact_expiry_allows_reclaim_and_stale_worker_cannot_acknowledge_or_fail_new_lease(): void
    {
        $clock = app(BusinessClock::class);
        $clock->freeze('2026-10-31T03:00:00Z');
        $event = $this->event();
        $outbox = app(OutboxService::class);
        $old = $outbox->claim($event->id, 60);
        $clock->freeze('2026-10-31T03:00:59.999999Z');
        $this->assertNull($outbox->claim($event->id));
        $clock->freeze('2026-10-31T03:01:00Z');
        $this->assertFalse($outbox->sent($old));
        $new = $outbox->claim($event->id, 60);
        $this->assertSame(2, $new->attempt);
        $this->assertFalse($outbox->sent($old));
        $this->assertFalse($outbox->failed($old));
        $this->assertTrue($outbox->sent($new));
        $this->assertSame(OutboxStatus::Sent, $event->fresh()->status);
        $this->assertNull($outbox->claim($event->id));
        $this->assertFalse($outbox->sent($new));
    }

    public function test_retry_backoff_and_terminal_attempts_are_bounded(): void
    {
        $clock = app(BusinessClock::class);
        $clock->freeze('2026-10-31T03:00:00Z');
        $event = $this->event();
        $outbox = app(OutboxService::class);
        for ($attempt = 1; $attempt <= OutboxService::MAX_ATTEMPTS; $attempt++) {
            $lease = $outbox->claim($event->id);
            $this->assertSame($attempt, $lease->attempt);
            $now = $clock->now();
            $this->assertTrue($outbox->failed($lease));
            $current = $event->fresh();
            $this->assertSame(min(3600, 30 * (2 ** ($attempt - 1))), $current->available_at->getTimestamp() - $now->getTimestamp());
            $this->assertNull($outbox->claim($event->id));
            $clock->freeze($current->available_at);
        }
        $this->assertSame(OutboxFailureReason::AttemptsExhausted->value, $event->fresh()->last_error);
        $this->assertNull($outbox->claim($event->id));
    }

    public function test_crash_on_last_attempt_becomes_terminal_after_lease_expiry(): void
    {
        $clock = app(BusinessClock::class);
        $clock->freeze('2026-10-31T03:00:00Z');
        $event = $this->event();
        DB::table('outbox_events')->where('id', $event->id)->update(['attempts' => 9]);
        $lease = app(OutboxService::class)->claim($event->id);
        $clock->freeze($lease->leaseUntil);
        $this->assertNull(app(OutboxService::class)->claim($event->id));
        $event->refresh();
        $this->assertSame(10, $event->attempts);
        $this->assertSame(OutboxStatus::Failed, $event->status);
        $this->assertNull($event->lease_until);
    }

    public function test_poison_payload_from_outside_the_service_is_never_delivered(): void
    {
        foreach ([['otp' => 'PRIVATE'], 'scalar-json'] as $index => $payload) {
            $event = $this->event('poison'.$index);
            DB::table('outbox_events')->where('id', $event->id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
            $this->assertNull(app(OutboxService::class)->claim($event->id));
            $this->assertSame(OutboxFailureReason::InvalidPayload->value, $event->fresh()->last_error);
            $this->assertNull(app(OutboxService::class)->claim($event->id));
        }
    }

    public function test_job_delivers_only_once_on_replay_and_serializes_only_the_event_identifier(): void
    {
        $event = $this->event(payload: ['display_name' => 'Synthetic recipient']);
        $delivery = new RecordingAtomicDelivery;
        $registry = app(OutboxDeliveryRegistry::class);
        $this->assertSame($registry, app(OutboxDeliveryRegistry::class));
        $registry->register('USER_UPDATED', $delivery);
        $job = new ProcessOutbox($event->id);
        $this->assertTrue($job->afterCommit);
        $this->assertStringNotContainsString('Synthetic recipient', serialize($job));
        $job->handle(app(OutboxService::class), $registry);
        $job->handle(app(OutboxService::class), $registry);
        $this->assertSame([$event->id], $delivery->delivered);
        $this->assertSame(OutboxStatus::Sent, $event->fresh()->status);
    }

    public function test_missing_or_failed_transport_never_becomes_fake_success_and_does_not_store_raw_error(): void
    {
        $event = $this->event();
        $registry = new OutboxDeliveryRegistry;
        (new ProcessOutbox($event->id))->handle(app(OutboxService::class), $registry);
        $event->refresh();
        $this->assertSame(OutboxFailureReason::HandlerMissing->value, $event->fresh()->last_error);
        $this->assertNull($event->sent_at);
        app(BusinessClock::class)->freeze($event->available_at);
        $delivery = new RecordingAtomicDelivery;
        $delivery->fail = true;
        $registry->register('USER_UPDATED', $delivery);
        (new ProcessOutbox($event->id))->handle(app(OutboxService::class), $registry);
        $event->refresh();
        $this->assertSame(OutboxFailureReason::DeliveryFailed->value, $event->fresh()->last_error);
        $this->assertSame(OutboxStatus::Failed, $event->status);
        $this->assertStringNotContainsString('PRIVATE', json_encode($event->getAttributes(), JSON_THROW_ON_ERROR));
        $this->assertSame(2, $event->attempts);
    }

    public function test_transport_can_finish_after_lease_loss_but_cannot_acknowledge_a_new_worker(): void
    {
        $clock = app(BusinessClock::class);
        $clock->freeze('2026-10-31T03:00:00Z');
        $event = $this->event();
        $registry = new OutboxDeliveryRegistry;
        $registry->register('USER_UPDATED', new class implements OutboxDelivery
        {
            public function deliver(OutboxLease $message): void
            {
                app(BusinessClock::class)->freeze($message->leaseUntil);
                app(OutboxService::class)->claim($message->eventId);
            }
        });
        (new ProcessOutbox($event->id))->handle(app(OutboxService::class), $registry);
        $event->refresh();
        $this->assertSame(OutboxStatus::Processing, $event->fresh()->status);
        $this->assertSame(2, $event->attempts);
        $this->assertNull($event->sent_at);
    }

    public function test_registry_rejects_accidental_handler_replacement(): void
    {
        $registry = new OutboxDeliveryRegistry;
        $registry->register('USER_UPDATED', new RecordingAtomicDelivery);
        $this->expectException(LogicException::class);
        $registry->register('USER_UPDATED', new RecordingAtomicDelivery);
    }
}

final class RecordingAtomicDelivery implements OutboxDelivery
{
    /** @var list<int> */
    public array $delivered = [];

    public bool $fail = false;

    public function deliver(OutboxLease $message): void
    {
        if ($this->fail) {
            throw new RuntimeException('PRIVATE OTP/password SQL /private/path that must not be stored.');
        }
        $this->delivered[] = $message->eventId;
    }
}
