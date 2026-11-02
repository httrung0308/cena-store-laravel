<?php

namespace App\Support;

/**
 * Vietnamese phone numbers as stored on accounts and addresses: separators removed, "+84" written as "0".
 */
final class PhoneNumber
{
    public const PATTERN = '/\A0[0-9]{9,10}\z/';

    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $digits = preg_replace('/[\s.\-()]/', '', trim($value));
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '+84') ? '0'.substr($digits, 3) : $digits;
    }
}
