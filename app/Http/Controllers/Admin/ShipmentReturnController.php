<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentReturnController extends Controller
{
    public function __construct(private readonly ShipmentReturnService $service) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.orders.index', array_filter([
            'status' => OrderStatus::Failed->value,
            'search' => $request->query('search'),
            'return_progress' => $request->query('return_progress'),
        ]));
    }

    public function show(Shipment $shipment): RedirectResponse
    {
        $shipment->loadMissing('order:id,order_status');
        abort_unless(
            $shipment->order?->order_status === OrderStatus::Failed
                && in_array($shipment->status, [ShipmentStatus::Returning, ShipmentStatus::ReturnReceived], true),
            404,
        );

        return redirect()->to(route('admin.orders.show', $shipment->order_id).'#xu-ly-hang-hoan');
    }

    public function receive(ShipmentReturnReceiveRequest $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validated();

        return $this->perform(
            fn () => $this->service->receiveUnit($shipment, (int) $data['unit_id'], $request->user(), $data['note']),
            $shipment->order_id,
            'Đã nhận đúng đôi vào kho cách ly.',
        );
    }

    public function incident(ShipmentReturnIncidentRequest $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validated();

        return $this->perform(
            fn () => $this->service->recordIncident($shipment, (int) $data['unit_id'], $request->user(), $data['incident_type'], $data['note']),
            $shipment->order_id,
            'Đã ghi nhận bất thường của kiện hàng.',
        );
    }

    public function restock(ShipmentReturnInspectionRequest $request, ShipmentReturnItem $returnItem): RedirectResponse
    {
        $returnItem->loadMissing('shipment:id,order_id');

        return $this->perform(
            fn () => $this->service->restock($returnItem, $request->user(), $request->validated('note')),
            $returnItem->shipment->order_id,
            'Đã kiểm tra đạt và nhập lại kho bán.',
        );
    }

    public function proposeWriteOff(ShipmentReturnInspectionRequest $request, ShipmentReturnItem $returnItem): RedirectResponse
    {
        $returnItem->loadMissing('shipment:id,order_id');

        return $this->perform(
            fn () => $this->service->proposeWriteOff($returnItem, $request->user(), $request->validated('note')),
            $returnItem->shipment->order_id,
            'Đã gửi đề xuất ghi hỏng để Admin phê duyệt.',
        );
    }

    public function approveWriteOff(ShipmentReturnInspectionRequest $request, ShipmentReturnItem $returnItem): RedirectResponse
    {
        $returnItem->loadMissing('shipment:id,order_id');

        return $this->perform(
            fn () => $this->service->approveWriteOff($returnItem, $request->user(), $request->validated('note')),
            $returnItem->shipment->order_id,
            'Admin đã phê duyệt ghi hỏng và ghi nhận tổn thất một lần.',
        );
    }

    private function perform(Closure $action, int $orderId, string $successMessage): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $exception) {
            $messages = collect($exception->errors)->flatten()->all();

            return back()->withErrors(['return' => $messages === [] ? [$exception->getMessage()] : $messages])->withInput();
        }

        return redirect()->to(route('admin.orders.show', $orderId).'#xu-ly-hang-hoan')->with('status', $successMessage);
    }
}
