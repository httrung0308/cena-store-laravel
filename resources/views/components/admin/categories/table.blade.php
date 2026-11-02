@props(['categories' => [], 'search' => '', 'searchUrl' => null, 'paginator' => null])
@php($groups = collect(App\Enums\GenderGroup::cases())->mapWithKeys(fn ($group) => [$group->value => $group->label()]))
<section class="space-y-6" aria-label="Danh mục">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="category-search" label="Tìm danh mục" placeholder="Tìm danh mục..." :value="$search" />
    @endif
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tên</th><th>Nhóm</th><th>Thứ tự</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
    @forelse ($categories as $category)
        <tr>
            <td><span class="font-semibold text-zinc-900">{{ $category['name'] ?? '' }}</span><span class="block text-xs text-zinc-500">{{ $category['slug'] ?? '' }}</span></td>
            <td>{{ $groups[$category['gender_group'] ?? ''] ?? ($category['gender_group'] ?? '') }}</td>
            <td>{{ $category['display_order'] ?? '' }}</td>
            <td>@if ($category['is_active'] ?? false)<span class="badge bg-green-100 text-green-600">Đang hiển thị</span>@else<span class="badge bg-zinc-100 text-zinc-600">Đang ẩn</span>@endif</td>
            <td><div class="flex flex-wrap gap-2">
                @if ($category['editUrl'] ?? null)<a class="btn-pill" href="{{ $category['editUrl'] }}">Sửa</a>@endif
                @if ($category['deactivateUrl'] ?? null)<form method="POST" action="{{ $category['deactivateUrl'] }}">@csrf @method('PATCH')<button class="btn-pill" type="submit">Ẩn</button></form>@endif
            </div></td>
        </tr>
    @empty
        <tr><td class="py-20 text-center text-zinc-500" colspan="5">Chưa có danh mục.</td></tr>
    @endforelse
    </tbody></table></div>
    @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
</section>
