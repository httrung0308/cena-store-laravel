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
<section x-data="{ ...adminAjaxList(), ...adminStatusToggle() }" class="space-y-6" aria-label="Danh mục">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="category-search" label="Tìm danh mục" placeholder="Tìm kiếm theo tên danh mục, slug..." :value="$search" :ajax="true" :active-chips="$activeChips">
            <label for="category-gender-filter" class="sr-only">Lọc theo đối tượng</label>
            <select id="category-gender-filter" name="gender_group" @change="submitFilter($el.form)"
                    class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">Tất cả đối tượng</option>
                @foreach (App\Enums\GenderGroup::cases() as $groupCase)
                    <option value="{{ $groupCase->value }}" @selected($genderGroup === $groupCase->value)>{{ $groupCase->label() }}</option>
                @endforeach
            </select>

            <label for="category-active-filter" class="sr-only">Lọc theo trạng thái</label>
            <select id="category-active-filter" name="active" @change="submitFilter($el.form)"
                    class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">Tất cả trạng thái</option>
                <option value="1" @selected($active === '1')>Đang hiển thị</option>
                <option value="0" @selected($active === '0')>Đang ẩn</option>
            </select>
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
                        <span class="px-2.5 py-0.5 rounded-lg bg-surface border border-border font-mono font-bold text-muted">
                            Thứ tự: {{ $category['display_order'] ?? '—' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60">
                        <div>
                            @if ($category['deactivateUrl'] ?? null)
                                <form method="POST" action="{{ $category['deactivateUrl'] }}" @submit.prevent="submitToggle($event, 'category-{{ $category['id'] ?? $loop->index }}')" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" :disabled="isBusy()"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg border border-border bg-surface text-main hover:border-error/50 hover:bg-error-bg hover:text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                        <span>Ẩn</span>
                                    </button>
                                </form>
                            @elseif ($category['activateUrl'] ?? null)
                                <form method="POST" action="{{ $category['activateUrl'] }}" @submit.prevent="submitToggle($event, 'category-{{ $category['id'] ?? $loop->index }}')" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" :disabled="isBusy()"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Hiện</span>
                                    </button>
                                </form>
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
                <div class="p-10 text-center text-xs text-muted">Chưa có danh mục.</div>
            @endforelse
        </div>

        {{-- Desktop table layout (>= md) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4">Tên</th>
                        <th class="p-4 whitespace-nowrap">Nhóm</th>
                        <th class="p-4 text-center whitespace-nowrap">Thứ tự</th>
                        <th class="p-4 text-center whitespace-nowrap">Trạng thái</th>
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
                            <span class="bg-surface text-main border border-border px-2.5 py-1 rounded-lg font-mono font-bold text-[11px]">{{ $category['display_order'] ?? '' }}</span>
                        </td>
                        <td class="p-4 text-center whitespace-nowrap">
                            <div class="inline-flex flex-col items-center gap-1.5">
                                @if ($category['is_active'] ?? false)
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                        Đang hiển thị
                                    </span>
                                    @if ($category['deactivateUrl'] ?? null)
                                        <form method="POST" action="{{ $category['deactivateUrl'] }}" @submit.prevent="submitToggle($event, 'category-{{ $category['id'] ?? $loop->index }}')" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" :disabled="isBusy()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-border bg-surface text-main hover:border-error/50 hover:bg-error-bg hover:text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                </svg>
                                                <span>Ẩn</span>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                        Đang ẩn
                                    </span>
                                    @if ($category['activateUrl'] ?? null)
                                        <form method="POST" action="{{ $category['activateUrl'] }}" @submit.prevent="submitToggle($event, 'category-{{ $category['id'] ?? $loop->index }}')" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" :disabled="isBusy()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>Hiện</span>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
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
                    <tr><td class="p-12 text-center text-xs text-muted" colspan="5">Chưa có danh mục.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
    </div>
</section>
