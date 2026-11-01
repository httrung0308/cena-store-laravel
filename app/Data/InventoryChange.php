<?php

namespace App\Data;

use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;

/** Server-built intent; never hydrate this directly from a request. */
final readonly class InventoryChange
{
    public function __construct(
        public string $operationKey,
        public int $variantId,
        public InventoryAction $action,
        public InventoryReferenceType $referenceType,
        public ?int $referenceId,
        public mixed $quantity,
        public mixed $unitCostBasis = null,
        public ?int $actorId = null,
        public ?string $reason = null,
    ) {}
}
