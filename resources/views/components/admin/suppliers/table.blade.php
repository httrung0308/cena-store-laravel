@props(['suppliers' => [], 'searchUrl' => null, 'search' => '', 'paginator' => null])
<section class="space-y-4"><h2 class="text-xl font-semibold">Nhà cung cấp</h2>
    @if ($searchUrl)<form action="{{ $searchUrl }}" method="GET" class="flex gap-2"><label for="supplier-search" class="sr-only">Tìm nhà cung cấp</label><input id="supplier-search" class="input flex-1" name="search" value="{{ $search }}"><x-ui.button type="submit">Tìm</x-ui.button></form>@endif
    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-2">Mã</th><th class="p-2">Tên</th><th class="p-2">Liên hệ</th><th class="p-2">Trạng thái</th><th class="p-2">Thao tác</th></tr></thead><tbody>
    @forelse ($suppliers as $supplier)
        <tr class="border-b"><td class="p-2">{{ $supplier['code'] ?? '' }}</td><td class="p-2">{{ $supplier['name'] ?? '' }}</td><td class="p-2">{{ $supplier['phone'] ?? '' }}</td><td class="p-2">{{ ($supplier['is_active'] ?? false) ? 'Hoạt động' : 'Đã ngừng' }}</td>
        <td class="p-2">@if ($supplier['editUrl'] ?? null)<a class="underline" href="{{ $supplier['editUrl'] }}">Sửa</a>@endif @if ($supplier['deactivateUrl'] ?? null)<form action="{{ $supplier['deactivateUrl'] }}" method="POST">@csrf @method('PATCH')<button type="submit" class="underline">Ngừng hoạt động</button></form>@endif</td></tr>
    @empty<tr><td class="p-4" colspan="5">Chưa có nhà cung cấp.</td></tr>@endforelse
    </tbody></table></div>
    @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
</section>
