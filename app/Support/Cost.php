<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;

final class Cost
{
    public const MAXIMUM = '9999999999999999.9999';

    public static function normalize(mixed $cost): string
    {
        if ((! is_int($cost) && ! is_string($cost)) || ! preg_match('/^[0-9]{1,32}(?:\.[0-9]{1,4})?$/D', (string) $cost)
            || bccomp((string) $cost, self::MAXIMUM, 4) > 0) {
            throw new DomainException(ErrorCode::InvalidCost);
        }

        return bcadd((string) $cost, '0', 4);
    }

    public static function weightedAverage(mixed $existingQuantity, mixed $existingCost, mixed $incomingQuantity, mixed $incomingCost): string
    {
        $existing = self::quantity($existingQuantity);
        $incoming = self::quantity($incomingQuantity);
        $total = Money::add($existing, $incoming);
        if ($incoming === 0 || $total > 4294967295) {
            throw new DomainException(ErrorCode::InvalidQuantity);
        }
        $oldScaled = bcmul(self::normalize($existingCost), '10000', 0);
        $newScaled = bcmul(self::normalize($incomingCost), '10000', 0);
        $numerator = bcadd(bcmul($oldScaled, (string) $existing, 0), bcmul($newScaled, (string) $incoming, 0), 0);
        $scaledAverage = bcdiv($numerator, (string) $total, 0);
        if (bccomp(bcmul(bcmod($numerator, (string) $total, 0), '2', 0), (string) $total, 0) >= 0) {
            $scaledAverage = bcadd($scaledAverage, '1', 0);
        }

        return self::normalize(bcdiv($scaledAverage, '10000', 4));
    }

    public static function cogs(mixed $cost, mixed $quantity): int
    {
        return Money::roundHalfUp(bcmul(self::normalize($cost), (string) self::quantity($quantity), 4));
    }

    private static function quantity(mixed $quantity): int
    {
        if ((! is_int($quantity) && ! is_string($quantity)) || ! preg_match('/^[0-9]{1,10}$/D', (string) $quantity)
            || bccomp((string) $quantity, '4294967295', 0) > 0) {
            throw new DomainException(ErrorCode::InvalidQuantity);
        }

        return (int) $quantity;
    }
}
