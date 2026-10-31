<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use JsonException;

final class CanonicalPayload
{
    /** @param array<array-key, mixed> $payload */
    public static function fingerprint(array $payload): string
    {
        try {
            return hash('sha256', json_encode(self::normalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (JsonException) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
    }

    private static function normalize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 32) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s.u\Z');
        }
        if (is_array($value)) {
            $isList = array_is_list($value);
            if (! $isList) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as &$child) {
                $child = self::normalize($child, $depth + 1);
            }
            unset($child);

            return $isList ? $value : (object) $value;
        }
        if ($value === null || is_string($value) || is_int($value) || is_bool($value)) {
            return $value;
        }

        throw new DomainException(ErrorCode::InvalidPayload);
    }
}
