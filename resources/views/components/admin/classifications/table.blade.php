@props([
    'classifications' => [],
    'search' => '',
    'group' => '',
    'active' => '',
    'homeNav' => '',
    'homeNavCounts' => [],
    'homeNavLimit' => 6,
    'searchUrl' => null,
    'paginator' => null,
])
@php
    $silCount = (int) ($homeNavCounts['SILHOUETTE'] ?? 0);
    $actCount = (int) ($homeNavCounts['ACTIVITY'] ?? 0);
@endphp
<section x-data="adminStatusToggle()" class="space-y-5" aria-label="Danh mục phân loại">
    @if ($searchUrl)
        <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center justify-between">
            <form method="GET" action="{{ $searchUrl }}" class="flex-1 max-w-md">
                <label for="classification-search" class="sr-only">Tìm nhãn phân loại</label>
                <div class="relative">
                    <input id="classification-search" type="text" name="search" value="{{ $search }}" placeholder="Tìm theo tên nhãn, slug..." class="input w-full pl-9 text-xs border-border bg-surface text-main placeholder:text-muted">
                    <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                @if ($group)<input type="hidden" name="group" value="{{ $group }}">@endif
                @if ($active !== '')<input type="hidden" name="active" value="{{ $active }}">@endif
                @if ($homeNav !== '')<input type="hidden" name="home_nav" value="{{ $homeNav }}">@endif
            </form>

            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                <a href="{{ route('admin.classifications.index', array_filter(['search' => $search, 'active' => $active, 'home_nav' => $homeNav], fn ($v) => $v !== '')) }}"
                   class="px-3 py-1.5 rounded-lg border transition-colors {{ empty($group) ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                    Tất cả nhóm
                </a>
                <a href="{{ route('admin.classifications.index', array_filter(['group' => 'SILHOUETTE', 'search' => $search, 'active' => $active, 'home_nav' => $homeNav], fn ($v) => $v !== '')) }}"
                   class="px-3 py-1.5 rounded-lg border transition-colors {{ $group === 'SILHOUETTE' ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                    Kiểu dáng
                </a>
                <a href="{{ route('admin.classifications.index', array_filter(['group' => 'ACTIVITY', 'search' => $search, 'active' => $active, 'home_nav' => $homeNav], fn ($v) => $v !== '')) }}"
                   class="px-3 py-1.5 rounded-lg border transition-colors {{ $group === 'ACTIVITY' ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                    Mục đích sử dụng
                </a>
                <a href="{{ route('admin.classifications.index', array_filter(['group' => $group, 'search' => $search, 'active' => $active, 'home_nav' => $homeNav === '1' ? '' : '1'], fn ($v) => $v !== '')) }}"
                   class="px-3 py-1.5 rounded-lg border transition-colors {{ $homeNav === '1' ? 'bg-accent text-accent-text border-accent' : 'bg-surface text-muted border-border hover:text-main' }}">
                    Hiển thị menu &amp; trang chủ
                </a>
            </div>
        </div>
    @endif

    <x-admin.ajax-feedback />

    <div id="admin-list-region" x-ref="region" class="space-y-5">
    {{-- Thống kê giới hạn hiển thị tại menu Header & trang chủ --}}
    <div class="rounded-2xl border border-border bg-surface-card p-4 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="space-y-0.5">
            <h2 class="text-xs font-bold uppercase tracking-wider text-main">
                Hiển thị ở menu Header &amp; trang chủ
            </h2>
            <p class="text-xs text-muted">
                Mỗi nhóm chọn tối đa <strong class="text-main">{{ $homeNavLimit }} phân loại đang hoạt động</strong>; sắp xếp theo cột <strong>Thứ tự</strong> tăng dần.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-1.5 font-semibold text-main">
                <span class="text-muted">Kiểu dáng:</span>
                <span class="font-mono font-bold {{ $silCount >= $homeNavLimit ? 'text-accent-sub' : 'text-accent' }}">{{ $silCount }} / {{ $homeNavLimit }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-1.5 font-semibold text-main">
                <span class="text-muted">Mục đích:</span>
                <span class="font-mono font-bold {{ $actCount >= $homeNavLimit ? 'text-accent-sub' : 'text-accent' }}">{{ $actCount }} / {{ $homeNavLimit }}</span>
            </span>
        </div>
    </div>

    <div class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4">Tên nhãn &amp; Slug</th>
                        <th class="p-4 whitespace-nowrap">Nhóm phân loại</th>
                        <th class="p-4 text-center whitespace-nowrap">Thứ tự</th>
                        <th class="p-4 text-center whitespace-nowrap">Menu &amp; Trang chủ</th>
                        <th class="p-4 text-center whitespace-nowrap">Số sản phẩm</th>
                        <th class="p-4 text-center whitespace-nowrap">Trạng thái</th>
                        <th class="p-4 text-right whitespace-nowrap">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($classifications as $item)
                    @php
                        $groupCount = (int) ($homeNavCounts[$item->classification_group->value] ?? 0);
                        $canEnableHomeNav = $item->show_in_home_nav || ! $item->is_active || $groupCount < $homeNavLimit;
                    @endphp
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="p-4">
                            <strong class="font-bold text-main text-sm block">{{ $item->name }}</strong>
                            <span class="block text-[11px] text-muted font-mono mt-0.5">{{ $item->slug }}</span>
                            @if ($item->description)
                                <p class="text-[11px] text-muted/80 mt-1 line-clamp-1">{{ $item->description }}</p>
                            @endif
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            @if ($item->classification_group === App\Enums\ClassificationGroup::Silhouette)
                                <span class="inline-flex items-center whitespace-nowrap px-2.5 py-1 rounded-md bg-accent/10 text-accent font-semibold text-[11px]">
                                    {{ $item->classification_group->label() }}
                                </span>
                            @else
                                <span class="inline-flex items-center whitespace-nowrap px-2.5 py-1 rounded-md border border-accent/25 bg-surface text-main font-semibold text-[11px]">
                                    {{ $item->classification_group->label() }}
                                </span>
                            @endif
                        </td>
                        <td class="p-4 text-center whitespace-nowrap">
                            <span class="bg-surface text-main border border-border px-2 py-0.5 rounded font-mono font-bold text-[11px]">{{ $item->sort_order }}</span>
                        </td>
                        <td class="p-4 text-center whitespace-nowrap">
                            @if ($item->show_in_home_nav && $item->is_active)
                                <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-full bg-accent/15 text-accent border border-accent/30 font-bold text-[11px]">Đang hiển thị</span>
                            @elseif ($item->show_in_home_nav && ! $item->is_active)
                                <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-full bg-warning-bg text-warning-text border border-warning/30 font-bold text-[11px]" title="Đã chọn nhưng nhãn đang tạm ngưng nên không hiển thị ra storefront">Chờ kích hoạt</span>
                            @else
                                <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-full bg-surface text-muted border border-border font-semibold text-[11px]">Không hiển thị</span>
                            @endif
                        </td>
                        <td class="p-4 text-center whitespace-nowrap">
                            <span class="inline-flex items-center whitespace-nowrap px-2.5 py-1 rounded-full font-bold text-[11px] {{ $item->products_count > 0 ? 'bg-surface text-main border border-border' : 'text-muted/60' }}">
                                {{ $item->products_count }} sản phẩm
                            </span>
                        </td>
                        <td class="p-4 text-center whitespace-nowrap">
                            @if ($item->is_active)
                                <span class="inline-flex items-center whitespace-nowrap bg-success-bg text-success-text border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Hoạt động</span>
                            @else
                                <span class="inline-flex items-center whitespace-nowrap bg-surface text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">Tạm ngưng</span>
                            @endif
                        </td>
                        <td class="p-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <a class="px-2.5 py-1 text-accent hover:bg-surface rounded-lg font-bold transition-colors" href="{{ route('admin.classifications.edit', $item) }}">Sửa</a>

                                <form method="POST" action="{{ route('admin.classifications.toggle-home-nav', $item) }}" @submit.prevent="submitToggle($event, 'cls-nav-{{ $item->id }}')" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="show_in_home_nav" value="{{ $item->show_in_home_nav ? 0 : 1 }}">
                                    <button type="submit"
                                            @disabled(! $canEnableHomeNav)
                                            :disabled="isBusy() || {{ ! $canEnableHomeNav ? 'true' : 'false' }}"
                                            title="{{ ! $canEnableHomeNav ? 'Nhóm đã đạt tối đa '.$homeNavLimit.' mục đang hoạt động hiển thị ở menu & trang chủ' : '' }}"
                                            class="px-2.5 py-1 rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed {{ $item->show_in_home_nav ? 'text-muted hover:bg-surface hover:text-main' : 'text-accent hover:bg-accent/10' }}">
                                        {{ $item->show_in_home_nav ? 'Ẩn khỏi menu' : 'Hiện ở menu' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.classifications.toggle-active', $item) }}" @submit.prevent="submitToggle($event, 'cls-active-{{ $item->id }}')" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="active" value="{{ $item->is_active ? 0 : 1 }}">
                                    <button :disabled="isBusy()" class="px-2.5 py-1 {{ $item->is_active ? 'text-warning hover:bg-warning-bg' : 'text-success hover:bg-success-bg' }} rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed" type="submit">
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
                    <tr><td class="p-12 text-center text-xs text-muted" colspan="7">Chưa có nhãn phân loại nào phù hợp bộ lọc.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
    </div>
    </div>
</section>
