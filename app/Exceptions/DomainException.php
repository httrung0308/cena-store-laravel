<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use RuntimeException;

class DomainException extends RuntimeException
{
    /** @param array<string, list<string>> $errors Safe, user-facing field errors only. */
    public function __construct(public readonly ErrorCode $errorCode, public readonly array $errors = [])
    {
        parent::__construct($errorCode->message());
    }
}
