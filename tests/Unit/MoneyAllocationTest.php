<?php

namespace Tests\Unit;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Support\Cost;
use App\Support\Money;
use App\Support\ShoeSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyAllocationTest extends TestCase
{
    public function test_one_dong_over_three_units_has_exact_total_and_stable_unit_number_tie_break(): void
    {
        $units = [
            ['id' => 'third', 'unit_no' => 3, 'weight' => 100],
            ['id' => 'first', 'unit_no' => 1, 'weight' => 100],
            ['id' => 'second', 'unit_no' => 2, 'weight' => 100],
        ];
        $expected = ['first' => 1, 'second' => 0, 'third' => 0];
        $this->assertSame($expected, Money::allocate(1, $units));
        $this->assertSame($expected, Money::allocate(1, array_reverse($units)));
        $this->assertSame(1, array_sum($expected));
    }

    public function test_largest_remainder_prioritizes_fraction_before_tie_break_and_uses_gross_weights(): void
    {
        $this->assertSame(['a' => 0, 'b' => 1, 'c' => 1], Money::allocate(2, [
            ['id' => 'a', 'unit_no' => 1, 'weight' => 1],
            ['id' => 'b', 'unit_no' => 2, 'weight' => 2],
            ['id' => 'c', 'unit_no' => 3, 'weight' => 3],
        ]));
        $this->assertSame(['02' => 1, 2 => 1, 10 => 0, 'a' => 0], Money::allocate(2, [
            ['id' => 'a', 'unit_no' => 1, 'weight' => 1],
            ['id' => '10', 'unit_no' => 1, 'weight' => 1],
            ['id' => '02', 'unit_no' => 1, 'weight' => 1],
            ['id' => 2, 'unit_no' => 1, 'weight' => 1],
        ]));
    }

    public function test_allocations_conserve_every_dong_with_zero_and_extreme_products(): void
    {
        foreach ([0, 1, 2, 100, 1_000_000_000, PHP_INT_MAX] as $amount) {
            foreach ([[1, 1, 1], [0, 100, 200], [PHP_INT_MAX, PHP_INT_MAX, 1]] as $weights) {
                $units = array_map(fn (int $i): array => ['id' => 'u'.$i, 'unit_no' => $i + 1, 'weight' => $weights[$i]], [0, 1, 2]);
                $actual = Money::allocate($amount, $units);
                $sum = '0';
                foreach ($actual as $share) {
                    $this->assertIsInt($share);
                    $this->assertGreaterThanOrEqual(0, $share);
                    $sum = bcadd($sum, (string) $share, 0);
                }
                $this->assertSame((string) $amount, $sum);
                $this->assertSame($actual, Money::allocate($amount, array_reverse($units)));
            }
        }
        $this->assertSame([], Money::allocate(0, []));
        $this->assertSame(['a' => 0], Money::allocate(0, [['id' => 'a', 'unit_no' => 1, 'weight' => 0]]));
    }

    public function test_integer_vnd_rounds_half_up_once_without_float_or_cent_conversion(): void
    {
        $this->assertSame(1_000_000, Money::integer('001000000'));
        $this->assertSame(PHP_INT_MAX, Money::integer((string) PHP_INT_MAX));
        foreach (['0.4999' => 0, '0.5000' => 1, '1.5' => 2, '999999999.4999' => 999999999, '999999999.5000' => 1000000000] as $input => $expected) {
            $this->assertSame($expected, Money::roundHalfUp($input));
        }
        $this->assertSame(1_000_000_000, Money::multiply(100_000_000, 10));
        $this->assertSame(PHP_INT_MAX, Money::add(PHP_INT_MAX - 1, 1));
        $this->assertSame(0, Money::subtract(100, 100));
    }

    public function test_percentage_null_cap_zero_cap_and_large_intermediate_product_are_distinct(): void
    {
        $this->assertSame(100, Money::percentage(999, 10, null));
        $this->assertSame(0, Money::percentage(999, 10, 0));
        $this->assertSame(30, Money::percentage(999, 10, 30));
        $this->assertSame(999, Money::percentage(999, 100, 2000));
        $this->assertSame(1, Money::percentage(1, 50));
        $this->assertSame(PHP_INT_MAX, Money::percentage(PHP_INT_MAX, 100));
        $this->assertSame(0, Money::percentage(0, 100));
        $this->assertSame(1_000_000_000, Money::orderTotal(1_000_000_000));
    }

    public function test_wac_uses_four_decimal_strings_and_rounds_only_final_cogs_to_vnd(): void
    {
        $this->assertSame('0.0000', Cost::normalize(0));
        $this->assertSame('123.4000', Cost::normalize('00123.4'));
        $this->assertSame('166666.6667', Cost::weightedAverage(10, '100000', 20, '200000'));
        $this->assertSame('0.0001', Cost::weightedAverage(1, '0.0000', 1, '0.0001'));
        $this->assertSame('0.0000', Cost::weightedAverage(2, '0.0000', 1, '0.0001'));
        $this->assertSame('100.0000', Cost::weightedAverage(0, '999', 2, '100'));
        $this->assertSame('50.0000', Cost::weightedAverage(1, '100', 1, '0'));
        $this->assertSame(Cost::MAXIMUM, Cost::weightedAverage('2147483647', Cost::MAXIMUM, '2147483648', Cost::MAXIMUM));
        $this->assertSame(1, Cost::cogs('0.1667', 3));
        $this->assertSame(500000, Cost::cogs('166666.6667', 3));
        $this->assertSame('40.5', ShoeSize::normalize('040.5000'));
        $this->assertSame('40', ShoeSize::normalize('40.0'));
        $this->assertSame('42', ShoeSize::normalize(42));
    }

    #[DataProvider('invalidOperations')]
    public function test_invalid_and_overflow_inputs_are_rejected(string $method, array $arguments, ErrorCode $code): void
    {
        [$class, $operation] = explode('::', $method);
        try {
            $class::$operation(...$arguments);
            $this->fail('Invalid input was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->errorCode);
        }
    }

    public static function invalidOperations(): iterable
    {
        foreach ([1.0, true, null, -1, '1e3', '1.0', ' 1', '+1', '', []] as $index => $input) {
            yield 'integer '.$index => [Money::class.'::integer', [$input], ErrorCode::InvalidAmount];
        }
        yield 'integer overflow' => [Money::class.'::integer', ['9223372036854775808'], ErrorCode::AmountOverflow];
        yield 'sum overflow' => [Money::class.'::add', [PHP_INT_MAX, 1], ErrorCode::AmountOverflow];
        yield 'product overflow' => [Money::class.'::multiply', [PHP_INT_MAX, 2], ErrorCode::AmountOverflow];
        yield 'round overflow' => [Money::class.'::roundHalfUp', ['9223372036854775807.5'], ErrorCode::AmountOverflow];
        yield 'order limit' => [Money::class.'::orderTotal', [1000000001], ErrorCode::AmountOverflow];
        yield 'negative result' => [Money::class.'::subtract', [0, 1], ErrorCode::InvalidAmount];
        yield 'percentage above 100' => [Money::class.'::percentage', [100, 101], ErrorCode::InvalidAmount];
        yield 'float rounding input' => [Money::class.'::roundHalfUp', [0.5], ErrorCode::InvalidAmount];
        foreach ([1.5, '-1', '1.00001', '10000000000000000.0000'] as $index => $cost) {
            yield 'cost '.$index => [Cost::class.'::normalize', [$cost], ErrorCode::InvalidCost];
        }
        foreach ([0, -1, 1.5, '4294967296'] as $index => $quantity) {
            yield 'incoming qty '.$index => [Cost::class.'::weightedAverage', [0, '0', $quantity, '1'], ErrorCode::InvalidQuantity];
        }
        yield 'combined qty overflow' => [Cost::class.'::weightedAverage', ['4294967295', '0', 1, '1'], ErrorCode::InvalidQuantity];
        yield 'cogs overflow' => [Cost::class.'::cogs', [Cost::MAXIMUM, '4294967295'], ErrorCode::AmountOverflow];
        foreach ([40.5, '40.1', '40e0', '0', '-40', '40.55', '1000', ' 40'] as $index => $size) {
            yield 'size '.$index => [ShoeSize::class.'::normalize', [$size], ErrorCode::InvalidSize];
        }
        foreach ([[], [['id' => 'x', 'unit_no' => 1, 'weight' => 0]], [['id' => 'x', 'unit_no' => 0, 'weight' => 1]], [['id' => 'x', 'unit_no' => 1, 'weight' => 1], ['id' => 'x', 'unit_no' => 2, 'weight' => 1]], ['x' => ['id' => 'x', 'unit_no' => 1, 'weight' => 1]]] as $index => $units) {
            yield 'allocation '.$index => [Money::class.'::allocate', [1, $units], ErrorCode::InvalidAllocation];
        }
    }
}
