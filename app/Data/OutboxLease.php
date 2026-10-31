<?php

namespace App\Data;

use App\Enums\OutboxAggregateType;
use Carbon\CarbonImmutable;

final readonly class OutboxLease
{
    /** @param array<array-key, mixed> $payload */
    public function __construct(
        public int $eventId,
        public int $attempt,
        public CarbonImmutable $leaseUntil,
        public string $eventType,
        public OutboxAggregateType $aggregateType,
        public int $aggregateId,
        public string $dedupKey,
        public array $payload,
    ) {}
}
