@props(['suppliers' => [], 'searchUrl' => null, 'search' => '', 'paginator' => null])
<section class="space-y-6" aria-label="Nhà cung cấp">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="supplier-search" label="Tìm nhà cung cấp" placeholder="Tìm nhà cung cấp..." :value="$search" />
    @endif
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Mã</th><th>Tên</th><th>Liên hệ</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
    @forelse ($suppliers as $supplier)
        <tr>
            <td class="font-semibold text-zinc-900">{{ $supplier['code'] ?? '' }}</td>
            <td>{{ $supplier['name'] ?? '' }}@if ($supplier['address'] ?? null)<span class="block text-xs text-zinc-500">{{ $supplier['address'] }}</span>@endif</td>
            <td>{{ $supplier['phone'] ?? '' }}@if ($supplier['email'] ?? null)<span class="block text-xs text-zinc-500">{{ $supplier['email'] }}</span>@endif</td>
            <td>@if ($supplier['is_active'] ?? false)<span class="badge bg-green-100 text-green-600">Hoạt động</span>@else<span class="badge bg-zinc-100 text-zinc-600">Đã ngừng</span>@endif</td>
            <td><div class="flex flex-wrap gap-2">
                @if ($supplier['editUrl'] ?? null)<a class="btn-pill" href="{{ $supplier['editUrl'] }}">Sửa</a>@endif
                @if ($supplier['deactivateUrl'] ?? null)<form action="{{ $supplier['deactivateUrl'] }}" method="POST">@csrf @method('PATCH')<button type="submit" class="btn-pill">Ngừng hoạt động</button></form>@endif
            </div></td>
        </tr>
    @empty
        <tr><td class="py-20 text-center text-zinc-500" colspan="5">Chưa có nhà cung cấp.</td></tr>
    @endforelse
    </tbody></table></div>
    @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
</section>
