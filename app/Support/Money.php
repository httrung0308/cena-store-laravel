<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;

final class Money
{
    public static function integer(mixed $value): int
    {
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^[0-9]{1,64}$/D', (string) $value)) {
            throw new DomainException(ErrorCode::InvalidAmount);
        }
        if (bccomp((string) $value, (string) PHP_INT_MAX, 0) > 0) {
            throw new DomainException(ErrorCode::AmountOverflow);
        }

        return (int) $value;
    }

    public static function add(mixed ...$values): int
    {
        $sum = '0';
        foreach ($values as $value) {
            $sum = bcadd($sum, (string) self::integer($value), 0);
        }

        return self::integer($sum);
    }

    public static function multiply(mixed $amount, mixed $quantity): int
    {
        return self::integer(bcmul((string) self::integer($amount), (string) self::integer($quantity), 0));
    }

    public static function subtract(mixed $amount, mixed $discount): int
    {
        return self::integer(bcsub((string) self::integer($amount), (string) self::integer($discount), 0));
    }

    public static function roundHalfUp(mixed $decimal): int
    {
        if ((! is_int($decimal) && ! is_string($decimal)) || ! preg_match('/^([0-9]{1,64})(?:\.([0-9]{1,18}))?$/D', (string) $decimal, $parts)) {
            throw new DomainException(ErrorCode::InvalidAmount);
        }
        $fraction = $parts[2] ?? '';

        return self::integer(self::roundedRatio($parts[1].$fraction, '1'.str_repeat('0', strlen($fraction))));
    }

    public static function percentage(mixed $subtotal, mixed $percentage, mixed $cap = null): int
    {
        $subtotal = self::integer($subtotal);
        $percentage = self::integer($percentage);
        if ($percentage > 100) {
            throw new DomainException(ErrorCode::InvalidAmount);
        }
        $discount = self::integer(self::roundedRatio(bcmul((string) $subtotal, (string) $percentage, 0), '100'));

        return min($subtotal, $discount, $cap === null ? $subtotal : self::integer($cap));
    }

    public static function orderTotal(mixed $amount, mixed $maximum = 1000000000): int
    {
        $amount = self::integer($amount);
        if ($amount > self::integer($maximum)) {
            throw new DomainException(ErrorCode::AmountOverflow);
        }

        return $amount;
    }

    /**
     * @param  list<array{id: int|string, unit_no: int, weight: int|string}>  $units
     * @return array<int|string, int> Stable order: unit_no, numeric identity, then binary identity.
     */
    public static function allocate(mixed $amount, array $units): array
    {
        $amount = self::integer($amount);
        if (! array_is_list($units)) {
            throw new DomainException(ErrorCode::InvalidAllocation);
        }
        $rows = [];
        $seen = [];
        $weightSum = '0';
        foreach ($units as $unit) {
            if (! is_array($unit) || ! isset($unit['id'], $unit['unit_no'], $unit['weight'])
                || (! is_int($unit['id']) && ! is_string($unit['id']))
                || ! preg_match('/^[A-Za-z0-9:_-]{1,180}$/D', (string) $unit['id'])
                || ! is_int($unit['unit_no']) || $unit['unit_no'] < 1 || isset($seen[$unit['id']])) {
                throw new DomainException(ErrorCode::InvalidAllocation);
            }
            $seen[$unit['id']] = true;
            $weight = self::integer($unit['weight']);
            $weightSum = bcadd($weightSum, (string) $weight, 0);
            $rows[] = ['id' => $unit['id'], 'unit_no' => $unit['unit_no'], 'weight' => $weight, 'share' => 0, 'remainder' => '0'];
        }
        if ($weightSum === '0' && $amount !== 0) {
            throw new DomainException(ErrorCode::InvalidAllocation);
        }
        $allocated = '0';
        foreach ($rows as &$row) {
            if ($weightSum !== '0') {
                $numerator = bcmul((string) $amount, (string) $row['weight'], 0);
                $row['share'] = self::integer(bcdiv($numerator, $weightSum, 0));
                $row['remainder'] = bcmod($numerator, $weightSum, 0);
                $allocated = bcadd($allocated, (string) $row['share'], 0);
            }
        }
        unset($row);
        usort($rows, static fn (array $a, array $b): int => bccomp($b['remainder'], $a['remainder'], 0) ?: self::compareUnits($a, $b));
        $remaining = self::integer(bcsub((string) $amount, $allocated, 0));
        for ($index = 0; $index < $remaining; $index++) {
            $rows[$index]['share']++;
        }
        usort($rows, self::compareUnits(...));
        $result = [];
        foreach ($rows as $row) {
            $result[$row['id']] = $row['share'];
        }

        return $result;
    }

    private static function roundedRatio(string $numerator, string $denominator): string
    {
        $whole = bcdiv($numerator, $denominator, 0);
        if (bccomp(bcmul(bcmod($numerator, $denominator, 0), '2', 0), $denominator, 0) >= 0) {
            $whole = bcadd($whole, '1', 0);
        }

        return $whole;
    }

    /**
     * @param  array{id: int|string, unit_no: int}  $a
     * @param  array{id: int|string, unit_no: int}  $b
     */
    private static function compareUnits(array $a, array $b): int
    {
        if ($a['unit_no'] !== $b['unit_no']) {
            return $a['unit_no'] <=> $b['unit_no'];
        }
        $left = (string) $a['id'];
        $right = (string) $b['id'];
        $leftNumeric = ctype_digit($left);
        $rightNumeric = ctype_digit($right);
        if ($leftNumeric !== $rightNumeric) {
            return $leftNumeric ? -1 : 1;
        }

        return ($leftNumeric ? bccomp($left, $right, 0) : 0) ?: strcmp($left, $right);
    }
}
