<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ReturnDisposition;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderTransitionRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ShipmentReturnEvent;
use App\Models\ShipmentReturnItem;
use App\Services\OrderWorkflowService;
use App\Services\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Back-office order list, detail and the status transitions of the COD workflow.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderWorkflowService $workflow) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(OrderPaymentStatus::class)],
            'return_progress' => ['nullable', Rule::in([
                'awaiting_receive',
                'partially_received',
                'awaiting_inspection',
                'awaiting_write_off_approval',
                'completed',
            ])],
        ]);
        $orders = Order::query()->with(['items.units', 'shipment.shipmentReturnItems.events', 'shipment.returnEvents'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn ($inner) => $inner->whereRaw("order_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("shipping_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("shipping_phone LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('order_status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, string $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->when($filters['return_progress'] ?? null, fn (Builder $query, string $progress) => $this->applyReturnProgressFilter($query, $progress))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'filters' => $filters]);
    }

    public function show(Order $order): View
    {
        $order->load([
            'user',
            'items.units',
            'items.variant.color.thumbnail',
            'payments',
            'refunds',
            'shipment.deliveryProof',
            'shipment.shipmentReturnItems.unit.item',
            'shipment.shipmentReturnItems.variant.color.thumbnail',
            'shipment.shipmentReturnItems.receivedBy',
            'shipment.shipmentReturnItems.inspectedBy',
            'shipment.shipmentReturnItems.events.actor',
            'shipment.returnEvents.actor',
            'shipment.returnEvents.unit.item',
            'statusHistories' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'returnProgress' => $this->resolveReturnProgress($order),
        ]);
    }

    public function deliveryProof(Order $order): StreamedResponse
    {
        abort_unless($order->shipment?->deliveryProof !== null, 404);
        $proof = $order->shipment->deliveryProof;

        return Storage::disk('private')->response($proof->path, 'Ảnh minh chứng giao hàng', [
            'Content-Type' => $proof->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function approveRefund(Order $order, Refund $refund, Request $request, RefundService $refunds): RedirectResponse
    {
        abort_unless($refund->order_id === $order->id, 404);
        try {
            $refunds->approveCancellation($refund, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['refund' => collect($exception->errors)->flatten()->all() ?: [$exception->getMessage()]]);
        }

        return back()->with('status', 'Đã duyệt hoàn tiền mô phỏng. Không có tiền thật được chuyển.');
    }

    public function transition(OrderTransitionRequest $request, Order $order, string $action): RedirectResponse
    {
        $actor = $request->user();
        $expected = (string) $request->validated('expected_status');
        try {
            match ($action) {
                'confirm' => $this->workflow->confirmAndPack($order, $actor, $expected),
                'pack' => $this->workflow->pack($order, $actor, $expected),
                'ship' => $this->workflow->ship($order, $actor, $expected, (string) $request->validated('carrier_name'), $request->validated('tracking_code')),
                'deliver' => $this->workflow->deliver($order, $actor, $expected, $request->file('proof')),
                'cancel' => $this->workflow->cancel($order, $actor, $expected, (string) $request->validated('reason'), false),
                'fail' => $this->workflow->fail($order, $actor, $expected, (string) $request->validated('reason')),
            };
        } catch (DomainException $exception) {
            $messages = collect($exception->errors)->flatten()->all();

            return back()->withErrors(['order' => $messages === [] ? [$exception->getMessage()] : $messages])->withInput();
        }

        return redirect()->route('admin.orders.show', $order)->with('status', 'Đã cập nhật đơn hàng.');
    }

    private function applyReturnProgressFilter(Builder $query, string $progress): void
    {
        $query->where('order_status', OrderStatus::Failed->value);
        $fullyReceivedOrClosed = function (Builder $shipmentQuery): void {
            $shipmentQuery->where('status', '!=', ShipmentStatus::Returning->value)
                ->orWhereRaw('(SELECT COUNT(*) FROM shipment_return_items WHERE shipment_return_items.shipment_id = shipments.id) >= GREATEST(1, COALESCE((SELECT SUM(quantity) FROM order_items WHERE order_items.order_id = orders.id), 0))');
        };

        $query->whereHas('shipment', function (Builder $shipmentQuery) use ($progress, $fullyReceivedOrClosed): void {
            match ($progress) {
                'awaiting_receive' => $shipmentQuery
                    ->where('status', '!=', ShipmentStatus::Lost->value)
                    ->whereDoesntHave('shipmentReturnItems'),
                'partially_received' => $shipmentQuery
                    ->where('status', ShipmentStatus::Returning->value)
                    ->whereHas('shipmentReturnItems')
                    ->whereRaw('(SELECT COUNT(*) FROM shipment_return_items WHERE shipment_return_items.shipment_id = shipments.id) < GREATEST(1, COALESCE((SELECT SUM(quantity) FROM order_items WHERE order_items.order_id = orders.id), 0))'),
                'awaiting_write_off_approval' => $shipmentQuery
                    ->where($fullyReceivedOrClosed)
                    ->whereHas('shipmentReturnItems', function (Builder $itemQuery): void {
                        $itemQuery->where('disposition', ReturnDisposition::Pending->value)
                            ->whereHas('events', fn (Builder $eventQuery) => $eventQuery->where('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED));
                    }),
                'awaiting_inspection' => $shipmentQuery
                    ->where($fullyReceivedOrClosed)
                    ->whereHas('shipmentReturnItems', fn (Builder $itemQuery) => $itemQuery->where('disposition', ReturnDisposition::Pending->value))
                    ->whereDoesntHave('shipmentReturnItems', function (Builder $itemQuery): void {
                        $itemQuery->where('disposition', ReturnDisposition::Pending->value)
                            ->whereHas('events', fn (Builder $eventQuery) => $eventQuery->where('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED));
                    }),
                'completed' => $shipmentQuery
                    ->whereHas('shipmentReturnItems')
                    ->where($fullyReceivedOrClosed)
                    ->whereDoesntHave('shipmentReturnItems', fn (Builder $itemQuery) => $itemQuery->where('disposition', ReturnDisposition::Pending->value)),
                default => null,
            };
        });
    }

    /** @return array{key: string, label: string, badge_class: string}|null */
    private function resolveReturnProgress(Order $order): ?array
    {
        if ($order->order_status !== OrderStatus::Failed || $order->shipment === null) {
            return null;
        }

        $shipment = $order->shipment;
        $totalUnits = max(1, (int) $order->items->sum('quantity'));
        $returnItems = $shipment->shipmentReturnItems;
        $receivedCount = $returnItems->count();

        if ($receivedCount === 0) {
            if ($shipment->status === ShipmentStatus::Lost) {
                return [
                    'key' => 'lost',
                    'label' => 'Thất lạc',
                    'badge_class' => 'bg-error-bg text-error-text border border-error/30',
                ];
            }

            return [
                'key' => 'awaiting_receive',
                'label' => 'Chờ nhận',
                'badge_class' => 'bg-warning-bg text-warning-text border border-warning/30',
            ];
        }

        if ($receivedCount < $totalUnits && $shipment->status === ShipmentStatus::Returning) {
            return [
                'key' => 'partially_received',
                'label' => 'Đã nhận một phần',
                'badge_class' => 'bg-warning-bg text-warning-text border border-warning/30',
            ];
        }

        $pendingItems = $returnItems->where('disposition', ReturnDisposition::Pending);
        if ($pendingItems->isNotEmpty()) {
            $hasWriteOffProposal = $pendingItems->contains(
                fn (ShipmentReturnItem $item): bool => $item->events->contains('event_type', ShipmentReturnEvent::WRITE_OFF_PROPOSED)
            );

            return $hasWriteOffProposal
                ? [
                    'key' => 'awaiting_write_off_approval',
                    'label' => 'Hàng hỏng chờ xử lý',
                    'badge_class' => 'bg-error-bg text-error-text border border-error/30',
                ]
                : [
                    'key' => 'awaiting_inspection',
                    'label' => 'Chờ kiểm tra',
                    'badge_class' => 'bg-warning-bg text-warning-text border border-warning/30',
                ];
        }

        return [
            'key' => 'completed',
            'label' => 'Đã xử lý',
            'badge_class' => 'bg-success-bg text-success-text border border-success/30',
        ];
    }
}
