@props(['classifications' => [], 'search' => '', 'group' => '', 'active' => '', 'searchUrl' => null, 'paginator' => null])
<section class="space-y-6" aria-label="Danh mục phân loại">
    @if ($searchUrl)
        <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
            <form method="GET" action="{{ $searchUrl }}" class="flex-1 max-w-md">
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Tìm theo tên nhãn, slug..." class="input w-full pl-9 text-xs border-border bg-surface text-main placeholder:text-muted">
                    <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                @if ($group)<input type="hidden" name="group" value="{{ $group }}">@endif
                @if ($active !== '')<input type="hidden" name="active" value="{{ $active }}">@endif
            </form>

            <div class="flex items-center gap-2 overflow-x-auto text-xs font-semibold">
                <a href="{{ route('admin.classifications.index', array_filter(['search' => $search, 'active' => $active])) }}"
                   class="px-3 py-1.5 rounded-lg border transition-colors {{ empty($group) ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                    Tất cả nhóm
                </a>
                <a href="{{ route('admin.classifications.index', array_filter(['group' => 'SILHOUETTE', 'search' => $search, 'active' => $active])) }}"
                   class="px-3 py-1.5 rounded-lg border transition-colors {{ $group === 'SILHOUETTE' ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                    Kiểu dáng
                </a>
                <a href="{{ route('admin.classifications.index', array_filter(['group' => 'ACTIVITY', 'search' => $search, 'active' => $active])) }}"
                   class="px-3 py-1.5 rounded-lg border transition-colors {{ $group === 'ACTIVITY' ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                    Mục đích sử dụng
                </a>
            </div>
        </div>
    @endif

    <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4">Tên nhãn & Slug</th>
                        <th class="p-4">Nhóm phân loại</th>
                        <th class="p-4 text-center">Thứ tự</th>
                        <th class="p-4 text-center">Số sản phẩm</th>
                        <th class="p-4 text-center">Trạng thái</th>
                        <th class="p-4 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($classifications as $item)
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="p-4">
                            <strong class="font-bold text-main text-sm block">{{ $item->name }}</strong>
                            <span class="block text-[11px] text-muted font-mono mt-0.5">{{ $item->slug }}</span>
                            @if ($item->description)
                                <p class="text-[11px] text-muted/80 mt-1 line-clamp-1">{{ $item->description }}</p>
                            @endif
                        </td>
                        <td class="p-4">
                            @if ($item->classification_group === App\Enums\ClassificationGroup::Silhouette)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-accent/10 text-accent font-semibold text-[11px]">
                                    👟 {{ $item->classification_group->label() }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold text-[11px]">
                                    🏃 {{ $item->classification_group->label() }}
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-center">
                            <span class="bg-surface text-main border border-border px-2 py-0.5 rounded font-mono font-bold text-[11px]">{{ $item->sort_order }}</span>
                        </td>
                        <td class="p-4 text-center">
                            <span class="px-2.5 py-1 rounded-full font-bold text-[11px] {{ $item->products_count > 0 ? 'bg-surface text-main border border-border' : 'text-muted/60' }}">
                                {{ $item->products_count }} sản phẩm
                            </span>
                        </td>
                        <td class="p-4 text-center">
                            @if ($item->is_active)
                                <span class="bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Hoạt động</span>
                            @else
                                <span class="bg-muted/15 text-muted px-2.5 py-0.5 rounded-full font-bold text-[11px]">Tạm ngưng</span>
                            @endif
                        </td>
                        <td class="p-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold transition-colors" href="{{ route('admin.classifications.edit', $item) }}">Sửa</a>
                                
                                <form method="POST" action="{{ route('admin.classifications.toggle-active', $item) }}" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button class="px-2.5 py-1 {{ $item->is_active ? 'text-amber-600 hover:bg-amber-500/10' : 'text-success hover:bg-success-bg' }} rounded-lg font-bold transition-colors" type="submit">
                                        {{ $item->is_active ? 'Tạm ngưng' : 'Kích hoạt' }}
                                    </button>
                                </form>

                                @if ($item->products_count === 0)
                                    <form method="POST" action="{{ route('admin.classifications.destroy', $item) }}" class="inline-block" onsubmit="return confirm('Bạn có chắc muốn xóa nhãn này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="px-2.5 py-1 text-error hover:bg-error-bg rounded-lg font-bold transition-colors" type="submit">Xóa</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-12 text-center text-xs text-muted" colspan="6">Chưa có nhãn phân loại nào phù hợp bộ lọc.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
    </div>
</section>
