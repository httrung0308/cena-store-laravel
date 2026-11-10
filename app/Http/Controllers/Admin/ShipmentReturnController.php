<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ReturnDisposition;
use App\Enums\ShipmentStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShipmentReturnIncidentRequest;
use App\Http\Requests\Admin\ShipmentReturnInspectionRequest;
use App\Http\Requests\Admin\ShipmentReturnReceiveRequest;
use App\Models\Shipment;
use App\Models\ShipmentReturnItem;
use App\Services\ShipmentReturnService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ShipmentReturnController extends Controller
{
    public function __construct(private readonly ShipmentReturnService $service) {}

    public function index(): View
    {
        $items = ShipmentReturnItem::query()
            ->with(['shipment.order:id,order_code', 'unit.item', 'variant', 'receivedBy', 'events.actor'])
            ->where('disposition', ReturnDisposition::Pending)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $returningShipments = Shipment::query()
            ->with('order:id,order_code,shipping_name')
            ->where('status', ShipmentStatus::Returning)
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'shipments_page')
            ->withQueryString();

        return view('admin.shipment-returns.index', compact('items', 'returningShipments'));
    }

    public function show(Shipment $shipment): View
    {
        $shipment->load([
            'order.items.units',
            'shipmentReturnItems.unit.item',
            'shipmentReturnItems.variant',
            'shipmentReturnItems.receivedBy',
            'shipmentReturnItems.inspectedBy',
            'shipmentReturnItems.events.actor',
            'returnEvents.actor',
            'returnEvents.unit.item',
        ]);
        abort_unless(
            $shipment->order?->order_status === OrderStatus::Failed
                && in_array($shipment->status, [ShipmentStatus::Returning, ShipmentStatus::ReturnReceived], true),
            404,
        );

        return view('admin.shipment-returns.show', compact('shipment'));
    }

    public function receive(ShipmentReturnReceiveRequest $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validated();

        return $this->perform(
            fn () => $this->service->receiveUnit($shipment, (int) $data['unit_id'], $request->user(), $data['note']),
            'Đã nhận đúng đôi vào kho cách ly.',
        );
    }

    public function incident(ShipmentReturnIncidentRequest $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validated();

        return $this->perform(
            fn () => $this->service->recordIncident($shipment, (int) $data['unit_id'], $request->user(), $data['incident_type'], $data['note']),
            'Đã ghi nhận bất thường của kiện hàng.',
        );
    }

    public function restock(ShipmentReturnInspectionRequest $request, ShipmentReturnItem $returnItem): RedirectResponse
    {
        return $this->perform(
            fn () => $this->service->restock($returnItem, $request->user(), $request->validated('note')),
            'Đã kiểm tra đạt và nhập lại kho bán.',
        );
    }

    public function proposeWriteOff(ShipmentReturnInspectionRequest $request, ShipmentReturnItem $returnItem): RedirectResponse
    {
        return $this->perform(
            fn () => $this->service->proposeWriteOff($returnItem, $request->user(), $request->validated('note')),
            'Đã gửi đề xuất ghi hỏng để Admin phê duyệt.',
        );
    }

    public function approveWriteOff(ShipmentReturnInspectionRequest $request, ShipmentReturnItem $returnItem): RedirectResponse
    {
        return $this->perform(
            fn () => $this->service->approveWriteOff($returnItem, $request->user(), $request->validated('note')),
            'Admin đã phê duyệt ghi hỏng và ghi nhận tổn thất một lần.',
        );
    }

    private function perform(Closure $action, string $successMessage): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $exception) {
            $messages = collect($exception->errors)->flatten()->all();

            return back()->withErrors(['return' => $messages === [] ? [$exception->getMessage()] : $messages])->withInput();
        }

        return back()->with('status', $successMessage);
    }
}
