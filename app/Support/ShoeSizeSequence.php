<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class ShoeSizeSequence
{
    /** @return list<string> */
    public static function between(string $first, string $last): array
    {
        $start = ShoeSize::normalize($first);
        $end = ShoeSize::normalize($last);

        if (bccomp($start, $end, 1) > 0) {
            throw new InvalidArgumentException('Size interval is reversed.');
        }

        $result = [];
        for ($size = $start; bccomp($size, $end, 1) <= 0; $size = bcadd($size, '0.5', 1)) {
            $result[] = ShoeSize::normalize($size);
        }

        return $result;
    }
}
