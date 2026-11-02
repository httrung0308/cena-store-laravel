@extends('layouts.admin')
@section('title','Chi tiết phiếu nhập')
@section('content')
@if($errors->any())<div role="alert" class="mb-4 border border-red-400 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="min-w-0 space-y-5 break-words"><p class="break-all font-semibold">{{ $import->import_code }}</p><p class="text-xl font-semibold">{{ $import->status->label() }}</p><p>Nhà cung cấp: {{ $import->supplier_code_snapshot }} — {{ $import->supplier_name_snapshot }}</p><p>Người tạo: {{ $import->createdBy?->full_name }}</p>
@if($import->posted_at)<p>Ghi sổ: {{ $import->posted_at->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i') }} — {{ $import->postedBy?->full_name }}</p>@endif
<p class="whitespace-pre-wrap">{{ $import->note }}</p>
<div class="overflow-x-auto"><table class="w-full min-w-[600px] text-left"><thead><tr><th>SKU snapshot</th><th>Số lượng</th><th>Giá nhập/đôi</th><th>Thành tiền</th></tr></thead><tbody>
@foreach($import->items as $line)<tr class="border-b"><td class="p-3">{{ $line->sku_snapshot }}</td><td>{{ $line->quantity }}</td><td>{{ number_format($line->unit_cost_amount,0,',','.') }} ₫</td><td>{{ number_format($line->subtotal_amount,0,',','.') }} ₫</td></tr>@endforeach
</tbody></table></div><p class="font-semibold">Tổng {{ $import->total_quantity }} đôi — {{ number_format($import->total_cost_amount,0,',','.') }} ₫</p>
@if($import->status===App\Enums\StockImportStatus::Draft)
<a class="underline" href="{{ route('admin.stock-imports.edit',$import) }}">Sửa nháp</a>
<form method="POST" action="{{ route('admin.stock-imports.post',$import) }}">@csrf<button class="btn">Ghi sổ nhập kho</button></form>
<form class="space-y-2" method="POST" action="{{ route('admin.stock-imports.cancel',$import) }}">@csrf<label class="block">Lý do hủy <input class="input w-full max-w-xl" name="reason" required maxlength="500"></label><button class="underline">Hủy phiếu nháp</button></form>
@else<p>Phiếu kết thúc, không sửa hoặc xóa. Sai sót phiếu đã ghi sổ phải được xử lý theo quy trình điều chỉnh kho.</p>@endif
<a class="underline" href="{{ route('admin.stock-imports.index') }}">Danh sách phiếu</a></div>
@endsection
