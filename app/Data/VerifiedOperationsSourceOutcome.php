<?php

namespace App\Data;

use App\Enums\OperationsSourceType;
use InvalidArgumentException;

final readonly class VerifiedOperationsSourceOutcome
{
    public function __construct(
        public OperationsSourceType $sourceType,
        public string $sourceEventKey,
        public string $outcomeCode,
    ) {
        if (trim($sourceEventKey) === '' || strlen($sourceEventKey) > 191
            || preg_match('/^[A-Z][A-Z0-9_]{1,63}$/', $outcomeCode) !== 1) {
            throw new InvalidArgumentException('Verified operations outcome is invalid.');
        }
    }
}
