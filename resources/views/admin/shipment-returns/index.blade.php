@extends('layouts.admin')
@section('title', 'Hàng Hoàn Cách Ly')
@section('eyebrow', 'QUẢN TRỊ KHO')
@php($tz = (string) config('store.business_timezone'))
@section('content')
    <div class="space-y-8">
        <section class="space-y-4">
            <div>
                <h2 class="text-xl font-semibold text-main">Chuyến hàng đang hoàn</h2>
                <p class="mt-1 text-sm text-muted">Nhận từng đôi theo mã đơn và đơn vị hàng. “Đôi #” là số dòng trong đơn, không phải số sê-ri vật lý.</p>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table min-w-[680px]"><thead><tr><th>Đơn hàng</th><th>Người nhận</th><th>Thất bại lúc</th><th>Trạng thái</th><th></th></tr></thead><tbody>
                    @forelse ($returningShipments as $shipment)
                        <tr>
                            <td class="font-semibold text-main">{{ $shipment->order->order_code }}</td>
                            <td class="text-main">{{ $shipment->order->shipping_name }}</td>
                            <td class="text-main">{{ $shipment->failed_at?->setTimezone($tz)->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-main">{{ $shipment->status->label() }}</td>
                            <td class="text-right"><a class="text-accent font-semibold hover:underline" href="{{ route('admin.shipment-returns.show', $shipment) }}">Mở xử lý</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-muted">Không có chuyến hàng đang hoàn.</td></tr>
                    @endforelse
                </tbody></table>
                <x-ui.pagination :paginator="$returningShipments" />
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-xl font-semibold text-main">Đôi đang cách ly chờ kiểm tra</h2>
                <p class="mt-1 text-sm text-muted">Hàng nhận từ giao thất bại chưa thể bán cho đến khi được nhập lại kho bán.</p>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table min-w-[680px]"><thead><tr><th>Đơn hàng</th><th>Sản phẩm / SKU</th><th>Đôi</th><th>Nhận lúc</th><th>Ghi chú tiếp nhận</th><th></th></tr></thead><tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="font-semibold text-main">{{ $item->shipment->order->order_code }}</td>
                            <td class="text-main">{{ $item->unit->item->product_name_snapshot }} · {{ $item->unit->item->color_name_snapshot }} / {{ $item->unit->item->size_value_snapshot }}<span class="block text-xs text-muted">{{ $item->unit->item->sku_snapshot }}</span></td>
                            <td class="text-main">#{{ $item->unit->unit_no }}</td>
                            <td class="text-main">{{ $item->received_at->setTimezone($tz)->format('d/m/Y H:i') }}</td>
                            <td class="max-w-xs whitespace-normal text-main">{{ $item->note }}</td>
                            <td class="text-right"><a class="text-accent font-semibold hover:underline" href="{{ route('admin.shipment-returns.show', $item->shipment) }}">Kiểm tra</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-muted">Không có đôi nào đang chờ kiểm tra.</td></tr>
                    @endforelse
                </tbody></table>
                <x-ui.pagination :paginator="$items" />
            </div>
        </section>
    </div>
@endsection
