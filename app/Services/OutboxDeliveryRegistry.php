<?php

namespace App\Services;

use App\Contracts\OutboxDelivery;
use App\Data\OutboxLease;
use App\Exceptions\MissingOutboxDelivery;
use LogicException;

final class OutboxDeliveryRegistry
{
    /** @var array<string, OutboxDelivery> */
    private array $deliveries = [];

    public function register(string $eventType, OutboxDelivery $delivery): void
    {
        if (isset($this->deliveries[$eventType])) {
            throw new LogicException('An outbox delivery is already registered for this event type.');
        }
        $this->deliveries[$eventType] = $delivery;
    }

    public function deliver(OutboxLease $message): void
    {
        $delivery = $this->deliveries[$message->eventType] ?? throw new MissingOutboxDelivery;
        $delivery->deliver($message);
    }
}
