<?php

namespace App\Contracts;

use App\Data\OutboxLease;

interface OutboxDelivery
{
    /** Deliver a notification only; never execute inventory, payment or ledger mutations. */
    public function deliver(OutboxLease $message): void;
}
