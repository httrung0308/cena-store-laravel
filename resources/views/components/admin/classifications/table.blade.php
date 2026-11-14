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
    $totalCount = $paginator ? $paginator->total() : count($classifications);
    $groupLabels = [
        'SILHOUETTE' => 'Kiểu dáng',
        'ACTIVITY' => 'Mục đích sử dụng',
    ];
    $activeChips = [];
    if (filled($search)) {
        $activeChips[] = [
            'label' => 'Từ khóa: '.$search,
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if (filled($group)) {
        $activeChips[] = [
            'label' => 'Nhóm: '.($groupLabels[$group] ?? $group),
            'url' => request()->fullUrlWithQuery(['group' => null, 'page' => null]),
        ];
    }
    if ($active !== '') {
        $activeChips[] = [
            'label' => 'Trạng thái: '.($active === '1' ? 'Hoạt động' : 'Tạm ngưng'),
            'url' => request()->fullUrlWithQuery(['active' => null, 'page' => null]),
        ];
    }
    if ($homeNav !== '') {
        $activeChips[] = [
            'label' => 'Menu: '.($homeNav === '1' ? 'Hiển thị menu' : 'Không hiển thị'),
            'url' => request()->fullUrlWithQuery(['home_nav' => null, 'page' => null]),
        ];
    }
@endphp
<section x-data="{ ...adminAjaxList(), ...adminStatusToggle() }" class="space-y-5" aria-label="Danh mục phân loại">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="classification-search" label="Tìm nhãn phân loại" placeholder="Tìm theo tên nhãn, slug..." :value="$search" :ajax="true" :active-chips="$activeChips">
            <label for="classification-group-filter" class="sr-only">Lọc theo nhóm phân loại</label>
            <select id="classification-group-filter" name="group" @change="submitFilter($el.form)"
                    class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">Tất cả nhóm</option>
                <option value="SILHOUETTE" @selected($group === 'SILHOUETTE')>Kiểu dáng</option>
                <option value="ACTIVITY" @selected($group === 'ACTIVITY')>Mục đích sử dụng</option>
            </select>

            <label for="classification-active-filter" class="sr-only">Lọc theo trạng thái</label>
            <select id="classification-active-filter" name="active" @change="submitFilter($el.form)"
                    class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">Tất cả trạng thái</option>
                <option value="1" @selected($active === '1')>Hoạt động</option>
                <option value="0" @selected($active === '0')>Tạm ngưng</option>
            </select>

            <label for="classification-homenav-filter" class="sr-only">Lọc hiển thị menu</label>
            <select id="classification-homenav-filter" name="home_nav" @change="submitFilter($el.form)"
                    class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">Tất cả vị trí</option>
                <option value="1" @selected($homeNav === '1')>Hiển thị menu &amp; trang chủ</option>
                <option value="0" @selected($homeNav === '0')>Không hiển thị menu</option>
            </select>
        </x-admin.search-form>
    @endif

    <x-admin.ajax-feedback />

    <div id="admin-list-region" x-ref="region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none': listLoading }" class="space-y-5 transition-opacity">
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
            <div class="px-4 py-3 border-b border-border/70 bg-surface/40 flex items-center justify-between text-xs text-muted">
                <span>Tìm thấy <strong class="text-main font-bold">{{ number_format($totalCount) }}</strong> mục</span>
            </div>
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
                            <td class="p-4 max-w-[18rem]">
                                <strong class="font-bold text-main text-sm block break-words">{{ $item->name }}</strong>
                                <span class="block text-[11px] text-muted font-mono mt-0.5 truncate">{{ $item->slug }}</span>
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
                                <div class="inline-flex flex-col items-center gap-1.5">
                                    @if ($item->is_active)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success-text border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                            Hoạt động
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap bg-surface text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                            Tạm ngưng
                                        </span>
                                    @endif

                                    <form method="POST" action="{{ route('admin.classifications.toggle-active', $item) }}" @submit.prevent="submitToggle($event, 'cls-active-{{ $item->id }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="active" value="{{ $item->is_active ? 0 : 1 }}">
                                        @if ($item->is_active)
                                            <button :disabled="isBusy()" type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-border bg-surface text-main hover:border-warning/50 hover:bg-warning-bg hover:text-warning-text transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Tạm ngưng</span>
                                            </button>
                                        @else
                                            <button :disabled="isBusy()" type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Kích hoạt</span>
                                            </button>
                                        @endif
                                    </form>
                                </div>
                            </td>
                            <td class="p-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" href="{{ route('admin.classifications.edit', $item) }}">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                        <span>Sửa</span>
                                    </a>

                                    <form method="POST" action="{{ route('admin.classifications.toggle-home-nav', $item) }}" @submit.prevent="submitToggle($event, 'cls-nav-{{ $item->id }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="show_in_home_nav" value="{{ $item->show_in_home_nav ? 0 : 1 }}">
                                        <button type="submit"
                                                @disabled(! $canEnableHomeNav)
                                                :disabled="isBusy() || {{ ! $canEnableHomeNav ? 'true' : 'false' }}"
                                                title="{{ ! $canEnableHomeNav ? 'Nhóm đã đạt tối đa '.$homeNavLimit.' mục đang hoạt động hiển thị ở menu & trang chủ' : '' }}"
                                                class="px-2.5 py-1.5 rounded-lg font-bold transition-colors disabled:opacity-40 disabled:cursor-not-allowed {{ $item->show_in_home_nav ? 'text-muted hover:bg-surface hover:text-main' : 'text-accent hover:bg-accent/10' }}">
                                            {{ $item->show_in_home_nav ? 'Ẩn khỏi menu' : 'Hiện ở menu' }}
                                        </button>
                                    </form>

                                    @if ($item->products_count === 0)
                                        <x-ui.dialog id="delete-classification-{{ $item->id }}" title="Xác nhận xóa nhãn phân loại" trigger-class="px-2.5 py-1.5 text-error hover:bg-error-bg rounded-lg font-bold transition-colors">
                                            <x-slot:trigger>Xóa</x-slot:trigger>
                                            <p class="text-xs text-muted leading-relaxed">
                                                Bạn có chắc chắn muốn xóa nhãn <strong class="text-main">{{ $item->name }}</strong>? Thao tác này không thể hoàn tác.
                                            </p>
                                            <div class="mt-5 flex items-center justify-end gap-2">
                                                <button type="button" x-on:click="closeDialog()" class="px-4 py-2 rounded-xl border border-border bg-surface text-xs font-bold text-main hover:bg-border/40 transition-colors">Hủy</button>
                                                <form method="POST" action="{{ route('admin.classifications.destroy', $item) }}" class="inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="px-4 py-2 rounded-xl bg-error text-white text-xs font-bold hover:bg-error/90 transition-colors">Xóa nhãn</button>
                                                </form>
                                            </div>
                                        </x-ui.dialog>
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
