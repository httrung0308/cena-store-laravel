<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;

final class BusinessClock
{
    private ?CarbonImmutable $frozenAt = null;

    /** @param array<string, int> $deadlines */
    public function __construct(private readonly string $timezone = 'Asia/Ho_Chi_Minh', private readonly array $deadlines = [])
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new DomainException(ErrorCode::InvalidDateRange);
        }
        foreach ($deadlines as $value) {
            if (! is_int($value) || $value < 1 || $value > 36600) {
                throw new DomainException(ErrorCode::InvalidDateRange);
            }
        }
    }

    public function now(): CarbonImmutable
    {
        return $this->frozenAt ?? CarbonImmutable::now('UTC');
    }

    public function freeze(DateTimeInterface|string $instant): void
    {
        $this->frozenAt = $this->instant($instant);
    }

    public function unfreeze(): void
    {
        $this->frozenAt = null;
    }

    public function instant(DateTimeInterface|string $instant): CarbonImmutable
    {
        if ($instant instanceof DateTimeInterface) {
            return CarbonImmutable::instance($instant)->utc();
        }
        if (! preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})[T ]([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.[0-9]{1,6})?(?:Z|[+-](?:(?:0[0-9]|1[0-3]):[0-5][0-9]|14:00))?$/D', $instant, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59) {
            throw new DomainException(ErrorCode::InvalidDateRange);
        }

        return CarbonImmutable::parse($instant, 'UTC')->utc();
    }

    public function beforeDeadline(DateTimeInterface|string $deadline): bool
    {
        return $this->now()->lessThan($this->instant($deadline));
    }

    public function withinWindow(DateTimeInterface|string|null $start, DateTimeInterface|string|null $end): bool
    {
        $start = $start === null ? null : $this->instant($start);
        $end = $end === null ? null : $this->instant($end);
        if ($start !== null && $end !== null && $end->lessThan($start)) {
            throw new DomainException(ErrorCode::InvalidDateRange);
        }

        return ($start === null || $this->now()->greaterThanOrEqualTo($start)) && ($end === null || $this->now()->lessThan($end));
    }

    public function exchangeDeadline(DateTimeInterface|string $deliveredAt, int $daysSnapshot): CarbonImmutable
    {
        if ($daysSnapshot < 0 || $daysSnapshot > 36600) {
            throw new DomainException(ErrorCode::InvalidDateRange);
        }

        return $this->instant($deliveredAt)->addDays($daysSnapshot);
    }

    public function warrantyDeadline(DateTimeInterface|string $deliveredAt, int $monthsSnapshot): CarbonImmutable
    {
        if ($monthsSnapshot < 0 || $monthsSnapshot > 1200) {
            throw new DomainException(ErrorCode::InvalidDateRange);
        }

        return $this->instant($deliveredAt)->setTimezone($this->timezone)->addMonthsNoOverflow($monthsSnapshot)->utc();
    }

    public function deadline(string $kind, DateTimeInterface|string|null $from = null): CarbonImmutable
    {
        $seconds = match ($kind) {
            'otp' => ($this->deadlines['otp_minutes'] ?? 15) * 60,
            'otp_resend' => $this->deadlines['otp_resend_seconds'] ?? 60,
            'online_payment' => ($this->deadlines['online_payment_minutes'] ?? 30) * 60,
            'cod_confirmation' => ($this->deadlines['cod_confirmation_hours'] ?? 24) * 3600,
            'rma_receive' => ($this->deadlines['rma_receive_days'] ?? 7) * 86400,
            default => throw new DomainException(ErrorCode::InvalidDateRange),
        };

        return ($from === null ? $this->now() : $this->instant($from))->addSeconds($seconds);
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable} Inclusive date inputs, exclusive UTC end. */
    public function vietnameseDateRange(string $firstDay, string $lastDay): array
    {
        foreach ([$firstDay, $lastDay] as $day) {
            if (! preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $day, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw new DomainException(ErrorCode::InvalidDateRange);
            }
        }
        if ($firstDay > $lastDay) {
            throw new DomainException(ErrorCode::InvalidDateRange);
        }

        return ['start' => CarbonImmutable::parse($firstDay, $this->timezone)->startOfDay()->utc(), 'end' => CarbonImmutable::parse($lastDay, $this->timezone)->startOfDay()->addDay()->utc()];
    }

    public function display(DateTimeInterface|string $instant): string
    {
        return $this->instant($instant)->setTimezone($this->timezone)->format('d/m/Y H:i:s');
    }
}
