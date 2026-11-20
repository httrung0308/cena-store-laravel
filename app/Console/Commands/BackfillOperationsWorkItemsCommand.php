<?php

namespace App\Console\Commands;

use App\Data\OperationsWorkItemDefinition;
use App\Enums\OperationsSourceType;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnDisposition;
use App\Enums\RoleSlug;
use App\Enums\ShipmentStatus;
use App\Models\OperationsWorkItem;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\ShipmentReturnEvent;
use App\Models\ShipmentReturnItem;
use App\Models\User;
use App\Services\OperationsWorkItemService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOperationsWorkItemsCommand extends Command
{
    protected $signature = 'operations:backfill-work-items {--dry-run : Preview missing work items without writing to the database}';

    protected $description = 'Idempotently backfill missing operations work items for currently pending downstream events.';

    public function handle(OperationsWorkItemService $workItems): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fallbackActor = $this->resolveFallbackActor();

        $pendingTasks = $this->collectMissingDefinitions($fallbackActor);

        $summary = [
            'failed_returns' => count($pendingTasks['failed_returns']),
            'write_off_approvals' => count($pendingTasks['write_off_approvals']),
            'refund_approvals' => count($pendingTasks['refund_approvals']),
            'return_incidents' => count($pendingTasks['return_incidents']),
        ];
        $total = array_sum($summary);

        if ($dryRun) {
            $this->info("Dry-run: {$total} missing work item(s) would be created.");
            $this->line("- failed_returns: {$summary['failed_returns']}");
            $this->line("- write_off_approvals: {$summary['write_off_approvals']}");
            $this->line("- refund_approvals: {$summary['refund_approvals']}");
            $this->line("- return_incidents: {$summary['return_incidents']}");

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('No missing operations work items found.');

            return self::SUCCESS;
        }

        if ($fallbackActor === null) {
            $this->error('Cannot backfill work items without an existing actor user.');

            return self::FAILURE;
        }

        $created = 0;
        DB::transaction(function () use ($pendingTasks, $workItems, $fallbackActor, &$created): void {
            foreach ($pendingTasks as $group) {
                foreach ($group as $entry) {
                    $definition = $entry['definition'];
                    $exists = OperationsWorkItem::query()
                        ->where('source_type', $definition->sourceType->value)
                        ->where('source_event_key', $definition->sourceEventKey)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $workItems->create($definition, $entry['actor'] ?? $fallbackActor);
                    $created++;
                }
            }
        });

        $this->info("Backfilled {$created} operations work item(s).");
        $this->line("- failed_returns: {$summary['failed_returns']}");
        $this->line("- write_off_approvals: {$summary['write_off_approvals']}");
        $this->line("- refund_approvals: {$summary['refund_approvals']}");
        $this->line("- return_incidents: {$summary['return_incidents']}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<array{definition: OperationsWorkItemDefinition, actor: ?User}>>
     */
    private function collectMissingDefinitions(?User $fallbackActor): array
    {
        $existingKeys = OperationsWorkItem::query()
            ->get(['source_type', 'source_event_key'])
            ->mapWithKeys(static fn (OperationsWorkItem $item): array => [
                $item->source_type->value.'|'.$item->source_event_key => true,
            ])
            ->all();

        $isMissing = static fn (OperationsSourceType $type, string $key): bool => ! isset($existingKeys[$type->value.'|'.$key]);

        $failedReturns = [];
        Shipment::query()
            ->with('order')
            ->where('status', ShipmentStatus::Returning->value)
            ->whereHas('order', static fn ($q) => $q->where('order_status', OrderStatus::Failed->value))
            ->get()
            ->each(function (Shipment $shipment) use (&$failedReturns, $isMissing, $fallbackActor): void {
                $key = "shipment:{$shipment->id}:failed-return";
                if (! $isMissing(OperationsSourceType::Shipment, $key) || $shipment->order === null) {
                    return;
                }
                $reason = $shipment->note ?: $shipment->order->cancel_reason ?: 'Giao thất bại';
                $occurredAt = $shipment->failed_at ?? $shipment->order->failed_at ?? $shipment->updated_at ?? $shipment->created_at ?? CarbonImmutable::now('UTC');

                $failedReturns[] = [
                    'definition' => new OperationsWorkItemDefinition(
                        sourceType: OperationsSourceType::Shipment,
                        sourceEventKey: $key,
                        sourceReference: $shipment->order->order_code,
                        sourceLabel: 'Hàng hoàn giao thất bại',
                        title: "Tiếp nhận hàng hoàn đơn {$shipment->order->order_code}",
                        content: "Đơn hàng {$shipment->order->order_code} giao thất bại ({$reason}). Cần tiếp nhận từng đôi vào cách ly và kiểm tra tình trạng.",
                        occurredAt: $occurredAt,
                    ),
                    'actor' => $fallbackActor,
                ];
            });

        $writeOffApprovals = [];
        ShipmentReturnItem::query()
            ->with(['shipment.order', 'events' => static fn ($q) => $q->where('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED)->with('actor')])
            ->where('disposition', ReturnDisposition::Pending->value)
            ->whereHas('events', static fn ($q) => $q->where('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED))
            ->get()
            ->each(function (ShipmentReturnItem $item) use (&$writeOffApprovals, $isMissing, $fallbackActor): void {
                $key = "shipment-return:{$item->id}:write-off-approval";
                $order = $item->shipment?->order;
                if (! $isMissing(OperationsSourceType::Inventory, $key) || $order === null) {
                    return;
                }
                $proposal = $item->events->first();
                $note = $proposal?->note ?: 'Không có ghi chú';
                $occurredAt = $proposal?->occurred_at ?? $item->updated_at ?? $item->created_at ?? CarbonImmutable::now('UTC');

                $writeOffApprovals[] = [
                    'definition' => new OperationsWorkItemDefinition(
                        sourceType: OperationsSourceType::Inventory,
                        sourceEventKey: $key,
                        sourceReference: $order->order_code,
                        sourceLabel: 'Phê duyệt ghi hỏng',
                        title: "Duyệt ghi hỏng hàng hoàn đơn {$order->order_code}",
                        content: "Nhân viên đề xuất ghi hỏng 1 đôi từ hàng hoàn cách ly (#{$item->id}). Lý do: {$note}",
                        occurredAt: $occurredAt,
                    ),
                    'actor' => $proposal?->actor ?? $fallbackActor,
                ];
            });

        $refundApprovals = [];
        Refund::query()
            ->with(['order', 'requestedBy'])
            ->where('status', RefundStatus::Requested->value)
            ->get()
            ->each(function (Refund $refund) use (&$refundApprovals, $isMissing, $fallbackActor): void {
                $key = "refund:{$refund->id}:approval";
                if (! $isMissing(OperationsSourceType::Payment, $key) || $refund->order === null) {
                    return;
                }
                $occurredAt = $refund->created_at ?? CarbonImmutable::now('UTC');

                $refundApprovals[] = [
                    'definition' => new OperationsWorkItemDefinition(
                        sourceType: OperationsSourceType::Payment,
                        sourceEventKey: $key,
                        sourceReference: $refund->order->order_code,
                        sourceLabel: 'Hoàn tiền chờ duyệt',
                        title: "Duyệt hoàn tiền đơn {$refund->order->order_code}",
                        content: "Đơn trả trước {$refund->order->order_code} đã hủy, cần Admin duyệt hoàn ".number_format($refund->amount, 0, ',', '.').' ₫.',
                        occurredAt: $occurredAt,
                    ),
                    'actor' => $refund->requestedBy ?? $fallbackActor,
                ];
            });

        $returnIncidents = [];
        ShipmentReturnEvent::query()
            ->with(['shipment.order', 'returnItem', 'actor'])
            ->whereIn('event_type', [
                ShipmentReturnEvent::MISSING_UNIT,
                ShipmentReturnEvent::SKU_MISMATCH,
                ShipmentReturnEvent::DAMAGED_UNIT,
                ShipmentReturnEvent::NO_EVIDENCE,
            ])
            ->get()
            ->each(function (ShipmentReturnEvent $event) use (&$returnIncidents, $isMissing, $fallbackActor): void {
                $key = "shipment-return-event:{$event->id}";
                $shipment = $event->shipment;
                $order = $shipment?->order;
                if (! $isMissing(OperationsSourceType::Shipment, $key) || $shipment === null || $order === null) {
                    return;
                }

                $isStillPending = ($event->returnItem === null && $shipment->status === ShipmentStatus::Returning)
                    || ($event->returnItem !== null && $event->returnItem->disposition === ReturnDisposition::Pending);

                if (! $isStillPending) {
                    return;
                }

                $note = $event->note ?: 'Không có ghi chú';
                $occurredAt = $event->occurred_at ?? $event->created_at ?? CarbonImmutable::now('UTC');

                $returnIncidents[] = [
                    'definition' => new OperationsWorkItemDefinition(
                        sourceType: OperationsSourceType::Shipment,
                        sourceEventKey: $key,
                        sourceReference: $order->order_code,
                        sourceLabel: 'Bất thường hàng hoàn',
                        title: "Xử lý bất thường {$event->event_type} · đơn {$order->order_code}",
                        content: "Ghi nhận sự cố {$event->event_type} cho đơn vị #{$event->order_item_unit_id} thuộc kiện hoàn #{$event->shipment_id}: {$note}",
                        occurredAt: $occurredAt,
                    ),
                    'actor' => $event->actor ?? $fallbackActor,
                ];
            });

        return [
            'failed_returns' => $failedReturns,
            'write_off_approvals' => $writeOffApprovals,
            'refund_approvals' => $refundApprovals,
            'return_incidents' => $returnIncidents,
        ];
    }

    private function resolveFallbackActor(): ?User
    {
        return User::query()
            ->whereHas('roles', static fn ($q) => $q->where('slug', RoleSlug::Admin->value))
            ->orderBy('id')
            ->first()
            ?? User::query()
                ->whereHas('roles', static fn ($q) => $q->where('slug', RoleSlug::Staff->value))
                ->orderBy('id')
                ->first();
    }
}
