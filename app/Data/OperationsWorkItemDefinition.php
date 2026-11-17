<?php

namespace App\Data;

use App\Enums\OperationsSourceType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class OperationsWorkItemDefinition
{
    public function __construct(
        public OperationsSourceType $sourceType,
        public string $sourceEventKey,
        public string $sourceReference,
        public string $sourceLabel,
        public string $title,
        public string $content,
        public CarbonImmutable $occurredAt,
    ) {
        if (trim($sourceEventKey) === '' || strlen($sourceEventKey) > 191
            || trim($sourceReference) === '' || strlen($sourceReference) > 100
            || trim($sourceLabel) === '' || strlen($sourceLabel) > 120
            || trim($title) === '' || strlen($title) > 200
            || trim($content) === '' || strlen($content) > 2000) {
            throw new InvalidArgumentException('Operations work item definition is invalid.');
        }
        if (filter_var($sourceReference, FILTER_VALIDATE_URL) !== false) {
            throw new InvalidArgumentException('Operations source references must not be URLs.');
        }
    }
}
