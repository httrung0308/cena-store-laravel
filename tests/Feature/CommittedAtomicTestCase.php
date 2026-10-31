<?php

namespace Tests\Feature;

use App\Enums\OutboxAggregateType;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Services\OutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Committed fixtures are required for after-commit and separate-process SQL tests. */
abstract class CommittedAtomicTestCase extends TestCase
{
    protected User $actor;

    protected string $namespace;

    /** @var list<int> */
    protected array $orderIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->namespace = 'atomic:'.Str::uuid().':';
        $this->actor = User::factory()->create()->refresh();
    }

    protected function tearDown(): void
    {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::table('outbox_events')->where('dedup_key', 'like', $this->namespace.'%')->delete();
            if ($this->orderIds !== []) {
                DB::table('sales_ledger_entries')->whereIn('order_id', $this->orderIds)->delete();
                DB::table('orders')->whereIn('id', $this->orderIds)->delete();
            }
            $this->actor->forceDelete();
        } finally {
            parent::tearDown();
        }
    }

    /** @param array<array-key, mixed> $payload */
    protected function event(string $suffix = 'event', array $payload = []): OutboxEvent
    {
        return DB::transaction(fn () => app(OutboxService::class)->enqueue($this->namespace.$suffix, 'USER_UPDATED', OutboxAggregateType::User, $this->actor->id, $payload));
    }

    protected function order(): Order
    {
        $order = Order::factory()->for($this->actor)->create();
        $this->orderIds[] = $order->id;

        return $order;
    }
}
