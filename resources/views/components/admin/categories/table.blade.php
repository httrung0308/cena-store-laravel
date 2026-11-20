@props(['categories' => [], 'search' => '', 'genderGroup' => '', 'active' => '', 'searchUrl' => null, 'paginator' => null])
@php
    $groups = collect(App\Enums\GenderGroup::cases())->mapWithKeys(fn ($group) => [$group->value => $group->label()]);
    $totalCount = $paginator ? $paginator->total() : count($categories);
    $activeChips = [];
    if (filled($search)) {
        $activeChips[] = [
            'label' => 'Từ khóa: '.$search,
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if (filled($genderGroup)) {
        $activeChips[] = [
            'label' => 'Đối tượng: '.($groups[$genderGroup] ?? $genderGroup),
            'url' => request()->fullUrlWithQuery(['gender_group' => null, 'page' => null]),
        ];
    }
    if ($active !== '') {
        $activeChips[] = [
            'label' => 'Trạng thái: '.($active === '1' ? 'Đang hiển thị' : 'Đang ẩn'),
            'url' => request()->fullUrlWithQuery(['active' => null, 'page' => null]),
        ];
    }
@endphp
<section x-data="{ ...adminAjaxList(), ...adminStatusToggle() }" class="space-y-6" aria-label="Đối tượng">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="category-search" label="Tìm đối tượng" placeholder="Tìm kiếm theo tên đối tượng, slug..." :value="$search" :ajax="true" :active-chips="$activeChips">
            <x-slot:filters>
                <x-admin.filter-select id="category-gender-filter" name="gender_group" label="Đối tượng:" :active="filled($genderGroup)">
                    <option value="">Tất cả</option>
                    @foreach (App\Enums\GenderGroup::cases() as $groupCase)
                        <option value="{{ $groupCase->value }}" @selected($genderGroup === $groupCase->value)>{{ $groupCase->label() }}</option>
                    @endforeach
                </x-admin.filter-select>

                <x-admin.filter-select id="category-active-filter" name="active" label="Trạng thái:" :active="$active !== ''">
                    <option value="">Tất cả</option>
                    <option value="1" @selected($active === '1')>Đang hiển thị</option>
                    <option value="0" @selected($active === '0')>Đang ẩn</option>
                </x-admin.filter-select>
            </x-slot:filters>
        </x-admin.search-form>
    @endif
    <x-admin.ajax-feedback />
    <div id="admin-list-region" x-ref="region" @click="handleRegionClick($event)" :class="{ 'opacity-60 pointer-events-none': listLoading }" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm transition-opacity">
        <div class="px-4 py-3 border-b border-border/70 bg-surface/40 flex items-center justify-between text-xs text-muted">
            <span>Tìm thấy <strong class="text-main font-bold">{{ number_format($totalCount) }}</strong> mục</span>
        </div>

        {{-- Mobile card layout (< md) --}}
        <div class="md:hidden divide-y divide-border">
            @forelse ($categories as $category)
                <article class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <strong class="font-bold text-main text-sm block break-words">{{ $category['name'] ?? '' }}</strong>
                            <span class="block text-[11px] text-muted font-mono mt-0.5 truncate">{{ $category['slug'] ?? '' }}</span>
                        </div>
                        @if ($category['is_active'] ?? false)
                            <span class="inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px] shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                Đang hiển thị
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px] shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                Đang ẩn
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="px-2.5 py-0.5 rounded-lg bg-surface border border-border font-bold text-main">
                            {{ $groups[$category['gender_group'] ?? ''] ?? ($category['gender_group'] ?? '—') }}
                        </span>
                        @if (($category['id'] ?? null) && \Illuminate\Support\Facades\Route::has('admin.categories.update-order'))
                            <span class="inline-flex items-center gap-1.5 text-muted">
                                <span>Thứ tự:</span>
                                <x-admin.inline-order-input
                                    :action="route('admin.categories.update-order', $category['id'])"
                                    :value="$category['display_order'] ?? 0"
                                    :toggle-key="'category-order-'.$category['id']"
                                    :label="'Thứ tự đối tượng '.($category['name'] ?? '')"
                                />
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-lg bg-surface border border-border font-mono font-bold text-muted">
                                Thứ tự: {{ $category['display_order'] ?? '—' }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60">
                        <div>
                            @php($catActive = (bool) ($category['is_active'] ?? false))
                            @php($catAction = $catActive ? ($category['deactivateUrl'] ?? null) : ($category['activateUrl'] ?? null))
                            @if ($catAction)
                                <x-admin.status-switch
                                    :active="$catActive"
                                    :action="$catAction"
                                    :toggle-key="'category-'.($category['id'] ?? $loop->index)"
                                    active-label="Đang hiển thị"
                                    inactive-label="Đang ẩn"
                                    :action-label="($catActive ? 'Ẩn đối tượng ' : 'Hiện đối tượng ').($category['name'] ?? '')"
                                />
                            @endif
                        </div>

                        @if ($category['editUrl'] ?? null)
                            <a class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" href="{{ $category['editUrl'] }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                                <span>Sửa</span>
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="p-10 text-center text-xs text-muted">Chưa có đối tượng.</div>
            @endforelse
        </div>

        {{-- Desktop table layout (>= md) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4">Tên</th>
                        <th class="p-4 whitespace-nowrap">Nhóm đối tượng</th>
                        <th class="p-4 text-center whitespace-nowrap">Thứ tự</th>
                        <th class="p-4 text-left whitespace-nowrap">Trạng thái</th>
                        <th class="p-4 text-right whitespace-nowrap">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($categories as $category)
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="p-4 max-w-[18rem]">
                            <strong class="font-bold text-main text-sm block break-words">{{ $category['name'] ?? '' }}</strong>
                            <span class="block text-[11px] text-muted font-mono mt-0.5 truncate">{{ $category['slug'] ?? '' }}</span>
                        </td>
                        <td class="p-4 font-bold text-main whitespace-nowrap">{{ $groups[$category['gender_group'] ?? ''] ?? ($category['gender_group'] ?? '') }}</td>
                        <td class="p-4 text-center whitespace-nowrap">
                            @if (($category['id'] ?? null) && \Illuminate\Support\Facades\Route::has('admin.categories.update-order'))
                                <x-admin.inline-order-input
                                    :action="route('admin.categories.update-order', $category['id'])"
                                    :value="$category['display_order'] ?? 0"
                                    :toggle-key="'category-order-'.$category['id']"
                                    :label="'Thứ tự đối tượng '.($category['name'] ?? '')"
                                />
                            @else
                                <span class="bg-surface text-main border border-border px-2.5 py-1 rounded-lg font-mono font-bold text-[11px]">{{ $category['display_order'] ?? '' }}</span>
                            @endif
                        </td>
                        <td class="p-4 text-left whitespace-nowrap">
                            @php($catActive = (bool) ($category['is_active'] ?? false))
                            @php($catAction = $catActive ? ($category['deactivateUrl'] ?? null) : ($category['activateUrl'] ?? null))
                            @if ($catAction)
                                <x-admin.status-switch
                                    :active="$catActive"
                                    :action="$catAction"
                                    :toggle-key="'category-'.($category['id'] ?? $loop->index)"
                                    active-label="Đang hiển thị"
                                    inactive-label="Đang ẩn"
                                    :action-label="($catActive ? 'Ẩn đối tượng ' : 'Hiện đối tượng ').($category['name'] ?? '')"
                                />
                            @else
                                <span @class([
                                    'text-xs font-semibold whitespace-nowrap',
                                    'text-success' => $catActive,
                                    'text-muted' => ! $catActive,
                                ])>{{ $catActive ? 'Đang hiển thị' : 'Đang ẩn' }}</span>
                            @endif
                        </td>
                        <td class="p-4 text-right whitespace-nowrap">
                            @if ($category['editUrl'] ?? null)
                                <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" href="{{ $category['editUrl'] }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                    </svg>
                                    <span>Sửa</span>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-12 text-center text-xs text-muted" colspan="5">Chưa có đối tượng.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
    </div>
</section>
