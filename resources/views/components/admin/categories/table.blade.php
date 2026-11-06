@props(['categories' => [], 'search' => '', 'searchUrl' => null, 'paginator' => null])
@php($groups = collect(App\Enums\GenderGroup::cases())->mapWithKeys(fn ($group) => [$group->value => $group->label()]))
<section class="space-y-6" aria-label="Danh mục">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="category-search" label="Tìm danh mục" placeholder="Tìm danh mục..." :value="$search" />
    @endif
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tên</th><th>Nhóm</th><th>Thứ tự</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
    @forelse ($categories as $category)
        <tr class="hover:bg-surface-alt/40 transition-colors">
            <td><span class="font-semibold text-main">{{ $category['name'] ?? '' }}</span><span class="block text-xs text-muted">{{ $category['slug'] ?? '' }}</span></td>
            <td class="text-main">{{ $groups[$category['gender_group'] ?? ''] ?? ($category['gender_group'] ?? '') }}</td>
            <td class="text-muted">{{ $category['display_order'] ?? '' }}</td>
            <td>@if ($category['is_active'] ?? false)<span class="badge bg-success-bg text-success-text border border-success/30">Đang hiển thị</span>@else<span class="badge bg-surface-alt text-muted border border-border">Đang ẩn</span>@endif</td>
            <td><div class="flex flex-wrap gap-2">
                @if ($category['editUrl'] ?? null)<a class="btn-pill" href="{{ $category['editUrl'] }}">Sửa</a>@endif
                @if ($category['deactivateUrl'] ?? null)<form method="POST" action="{{ $category['deactivateUrl'] }}">@csrf @method('PATCH')<button class="btn-pill text-error hover:bg-error-bg" type="submit">Ẩn</button></form>@endif
            </div></td>
        </tr>
    @empty
        <tr><td class="py-20 text-center text-muted" colspan="5">Chưa có danh mục.</td></tr>
    @endforelse
    </tbody></table></div>
    @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
</section>
