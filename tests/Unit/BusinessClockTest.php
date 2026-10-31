<?php

namespace Tests\Unit;

use App\Exceptions\DomainException;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BusinessClockTest extends TestCase
{
    public function test_deadlines_expire_at_the_exact_microsecond_and_clock_is_freezable(): void
    {
        $clock = new BusinessClock;
        $deadline = '2026-10-31T03:00:00.123456Z';
        $clock->freeze('2026-10-31T10:00:00.123455+07:00');
        $this->assertTrue($clock->beforeDeadline($deadline));
        $clock->freeze($deadline);
        $this->assertFalse($clock->beforeDeadline($deadline));
        $this->assertSame('UTC', $clock->now()->timezoneName);
        $this->assertSame('31/10/2026 10:00:00', $clock->display($deadline));
        $clock->freeze('2026-10-31T03:00:00.123457Z');
        $this->assertFalse($clock->beforeDeadline($deadline));
        $clock->unfreeze();
        $this->assertLessThan(3, abs($clock->now()->getTimestamp() - time()));
    }

    public function test_coupon_windows_have_inclusive_start_exclusive_end_and_nullable_bounds(): void
    {
        $clock = new BusinessClock;
        $clock->freeze('2026-10-31T03:00:00Z');
        $this->assertTrue($clock->withinWindow(null, null));
        $this->assertTrue($clock->withinWindow('2026-10-31T03:00:00Z', null));
        $this->assertFalse($clock->withinWindow(null, '2026-10-31T03:00:00Z'));
        $this->assertFalse($clock->withinWindow('2026-10-31T03:00:01Z', null));
        $this->assertFalse($clock->withinWindow('2026-10-31T03:00:00Z', '2026-10-31T03:00:00Z'));
    }

    public function test_exchange_days_and_month_end_warranty_follow_vietnamese_business_time(): void
    {
        $clock = new BusinessClock;
        $delivered = '2026-10-31T10:00:00+07:00';
        $this->assertSame('2026-11-07 03:00:00', $clock->exchangeDeadline($delivered, 7)->format('Y-m-d H:i:s'));
        $this->assertSame('2027-04-30 03:00:00', $clock->warrantyDeadline($delivered, 6)->format('Y-m-d H:i:s'));
        $this->assertSame('30/04/2027 00:30:00', $clock->display($clock->warrantyDeadline('2026-10-31T00:30:00+07:00', 6)));
        $this->assertSame('29/02/2028 00:30:00', $clock->display($clock->warrantyDeadline('2027-08-31T00:30:00+07:00', 6)));
        $this->assertSame('31/10/2026 10:00:00', $clock->display($clock->exchangeDeadline($delivered, 0)));
    }

    public function test_vietnamese_date_filters_produce_half_open_utc_ranges_including_last_microsecond(): void
    {
        $clock = new BusinessClock;
        $range = $clock->vietnameseDateRange('2026-10-31', '2026-11-01');
        $this->assertSame('2026-10-30 17:00:00.000000', $range['start']->format('Y-m-d H:i:s.u'));
        $this->assertSame('2026-11-01 17:00:00.000000', $range['end']->format('Y-m-d H:i:s.u'));
        $clock->freeze('2026-11-01T23:59:59.999999+07:00');
        $this->assertTrue($clock->withinWindow($range['start'], $range['end']));
        $clock->freeze('2026-11-02T00:00:00+07:00');
        $this->assertFalse($clock->withinWindow($range['start'], $range['end']));
    }

    public function test_default_and_injected_deadlines_use_snapshotted_or_frozen_instants(): void
    {
        $clock = new BusinessClock;
        $clock->freeze('2026-10-31T03:00:00Z');
        foreach (['otp' => 900, 'otp_resend' => 60, 'online_payment' => 1800, 'cod_confirmation' => 86400, 'rma_receive' => 604800] as $kind => $seconds) {
            $this->assertSame($clock->now()->getTimestamp() + $seconds, $clock->deadline($kind)->getTimestamp());
        }
        $custom = new BusinessClock(deadlines: ['otp_minutes' => 20]);
        $this->assertSame('2026-10-31 03:20:00', $custom->deadline('otp', $clock->now())->format('Y-m-d H:i:s'));
        $original = CarbonImmutable::parse('2026-10-31T10:00:00+07:00');
        $clock->freeze($original);
        $this->assertSame('+07:00', $original->format('P'));
        $this->assertSame('+00:00', $clock->now()->format('P'));
    }

    #[DataProvider('invalidClockOperations')]
    public function test_invalid_calendar_inputs_are_rejected_instead_of_silently_normalized(string $method, array $arguments): void
    {
        $this->expectException(DomainException::class);
        (new BusinessClock)->$method(...$arguments);
    }

    public static function invalidClockOperations(): iterable
    {
        foreach (['2026-02-29T00:00:00Z', '2026-10-31T24:00:00Z', 'tomorrow', '2026-10-31', '2026-10-31T00:00:00+14:30'] as $date) {
            yield $date => ['instant', [$date]];
        }
        yield 'reversed window' => ['withinWindow', ['2026-11-01T00:00:00Z', '2026-10-31T00:00:00Z']];
        yield 'reversed days' => ['vietnameseDateRange', ['2026-11-01', '2026-10-31']];
        yield 'invalid day' => ['vietnameseDateRange', ['2026-02-30', '2026-03-01']];
        yield 'negative exchange' => ['exchangeDeadline', ['2026-10-31T00:00:00Z', -1]];
        yield 'negative warranty' => ['warrantyDeadline', ['2026-10-31T00:00:00Z', -1]];
        yield 'unknown kind' => ['deadline', ['missing']];
    }

    public function test_invalid_config_cannot_overflow_deadline_multiplication(): void
    {
        $this->expectException(DomainException::class);
        new BusinessClock(deadlines: ['rma_receive_days' => PHP_INT_MAX]);
    }
}
