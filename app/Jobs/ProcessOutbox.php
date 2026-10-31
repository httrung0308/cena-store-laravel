<?php

namespace App\Jobs;

use App\Enums\ErrorCode;
use App\Enums\OutboxFailureReason;
use App\Exceptions\DomainException;
use App\Exceptions\MissingOutboxDelivery;
use App\Services\OutboxDeliveryRegistry;
use App\Services\OutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $eventId)
    {
        if ($eventId < 1) {
            throw new DomainException(ErrorCode::InvalidPayload);
        }
        $this->afterCommit();
    }

    public function handle(OutboxService $outbox, OutboxDeliveryRegistry $deliveries): void
    {
        $lease = $outbox->claim($this->eventId);
        if ($lease === null) {
            return;
        }
        try {
            $deliveries->deliver($lease);
        } catch (MissingOutboxDelivery) {
            $outbox->failed($lease, OutboxFailureReason::HandlerMissing);

            return;
        } catch (Throwable) {
            $outbox->failed($lease);

            return;
        }
        $outbox->sent($lease);
    }
}
