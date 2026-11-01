<?php

namespace App\Support;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;

final class OperationKey
{
    public static function validate(string $key): string
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9:._\/-]{0,179}$/D', $key)) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }

        return $key;
    }
}
