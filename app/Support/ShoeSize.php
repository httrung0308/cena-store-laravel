<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;

final class ShoeSize
{
    public static function normalize(mixed $size): string
    {
        if ((! is_int($size) && ! is_string($size)) || ! preg_match('/^[0-9]{1,6}(?:\.[0-9]{1,4})?$/D', (string) $size)
            || bccomp((string) $size, '0', 4) <= 0 || bccomp((string) $size, '999.9', 4) > 0
            || bcmod(bcmul((string) $size, '10000', 0), '5000', 0) !== '0') {
            throw new DomainException(ErrorCode::InvalidSize);
        }
        $normalized = bcadd((string) $size, '0', 1);

        return str_ends_with($normalized, '.0') ? substr($normalized, 0, -2) : $normalized;
    }
}
