@extends('layouts.admin')
@section('title','Phiếu nhập kho')
@section('content')
<a class="btn inline-block" href="{{ route('admin.stock-imports.create') }}">Tạo phiếu nháp</a>
<form method="GET" class="my-4 flex flex-wrap gap-2"><label>Tìm mã phiếu <input class="input" name="search" value="{{ request('search') }}"></label><label>Trạng thái <select class="input" name="status"><option value="">Tất cả</option>@foreach(App\Enums\StockImportStatus::cases() as $state)<option value="{{ $state->value }}" @selected(request('status')===$state->value)>{{ $state->label() }}</option>@endforeach</select></label><button class="btn">Lọc</button></form>
<div class="overflow-x-auto"><table class="w-full text-left"><thead><tr><th>Mã</th><th>Nhà cung cấp</th><th>Trạng thái</th><th>Số lượng</th><th>Tổng tiền nhập</th></tr></thead><tbody>
@forelse($imports as $import)<tr class="border-b"><td class="p-3"><a class="underline" href="{{ route('admin.stock-imports.show',$import) }}">{{ $import->import_code }}</a></td><td>{{ $import->supplier_name_snapshot }}</td><td>{{ $import->status->label() }}</td><td>{{ $import->total_quantity }}</td><td>{{ number_format($import->total_cost_amount,0,',','.') }} ₫</td></tr>
@empty<tr><td colspan="5">Chưa có phiếu nhập.</td></tr>@endforelse</tbody></table></div><x-ui.pagination :paginator="$imports" />
@endsection
