<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderTransitionRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Services\OrderWorkflowService;
use App\Services\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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
        ]);
        $orders = Order::query()->with('items')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn ($inner) => $inner->whereRaw("order_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("shipping_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("shipping_phone LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('order_status', $status))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'filters' => $filters]);
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.units', 'payments', 'refunds', 'shipment.deliveryProof', 'statusHistories' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]);

        return view('admin.orders.show', ['order' => $order]);
    }

    public function deliveryProof(Order $order): Response
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
}
