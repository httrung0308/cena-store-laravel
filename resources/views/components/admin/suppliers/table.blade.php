@props(['suppliers' => [], 'searchUrl' => null, 'search' => '', 'active' => '', 'paginator' => null])
@php
    $active = (string) ($active !== '' ? $active : request('active', ''));
    $totalCount = $paginator ? $paginator->total() : count($suppliers);
    $activeChips = [];
    if (filled($search)) {
        $activeChips[] = [
            'label' => 'Từ khóa: '.$search,
            'url' => request()->fullUrlWithQuery(['search' => null, 'page' => null]),
        ];
    }
    if ($active !== '') {
        $activeChips[] = [
            'label' => 'Trạng thái: '.($active === '1' ? 'Hoạt động' : 'Đã ngừng'),
            'url' => request()->fullUrlWithQuery(['active' => null, 'page' => null]),
        ];
    }
@endphp
<section x-data="{ ...adminAjaxList(), ...adminStatusToggle() }" class="space-y-6" aria-label="Nhà cung cấp">
    @if ($searchUrl)
        <x-admin.search-form :action="$searchUrl" id="supplier-search" label="Tìm nhà cung cấp" placeholder="Tìm kiếm theo mã, tên nhà cung cấp..." :value="$search" :ajax="true" :active-chips="$activeChips">
            <label for="supplier-active-filter" class="sr-only">Lọc theo trạng thái</label>
            <select id="supplier-active-filter" name="active" @change="submitFilter($el.form)"
                    class="bg-bg border border-border px-3 py-2 rounded-xl text-xs text-main font-semibold focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">Tất cả trạng thái</option>
                <option value="1" @selected($active === '1')>Hoạt động</option>
                <option value="0" @selected($active === '0')>Đã ngừng</option>
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
            @forelse ($suppliers as $supplier)
                <article class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="font-mono font-bold text-accent text-xs block">{{ $supplier['code'] ?? '' }}</span>
                            <strong class="font-bold text-main text-sm block break-words mt-0.5">{{ $supplier['name'] ?? '' }}</strong>
                            @if ($supplier['address'] ?? null)
                                <span class="block text-[11px] text-muted mt-0.5 break-words">{{ $supplier['address'] }}</span>
                            @endif
                        </div>
                        @if ($supplier['is_active'] ?? false)
                            <span class="inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px] shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                Hoạt động
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px] shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                Đã ngừng
                            </span>
                        @endif
                    </div>

                    <div class="text-xs text-muted space-y-0.5">
                        @if ($supplier['phone'] ?? null)
                            <p class="font-semibold text-main">{{ $supplier['phone'] }}</p>
                        @endif
                        @if ($supplier['email'] ?? null)
                            <p class="text-[11px] break-all">{{ $supplier['email'] }}</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60">
                        <div>
                            @if ($supplier['deactivateUrl'] ?? null)
                                <form action="{{ $supplier['deactivateUrl'] }}" method="POST" @submit.prevent="submitToggle($event, 'supplier-{{ $supplier['id'] ?? $loop->index }}')" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" :disabled="isBusy()"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg border border-border bg-surface text-main hover:border-error/50 hover:bg-error-bg hover:text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Ngừng hoạt động</span>
                                    </button>
                                </form>
                            @elseif ($supplier['activateUrl'] ?? null)
                                <form action="{{ $supplier['activateUrl'] }}" method="POST" @submit.prevent="submitToggle($event, 'supplier-{{ $supplier['id'] ?? $loop->index }}')" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" :disabled="isBusy()"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Kích hoạt</span>
                                    </button>
                                </form>
                            @endif
                        </div>

                        @if ($supplier['editUrl'] ?? null)
                            <a class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" href="{{ $supplier['editUrl'] }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                                <span>Sửa</span>
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="p-10 text-center text-xs text-muted">Chưa có nhà cung cấp.</div>
            @endforelse
        </div>

        {{-- Desktop table layout (>= md) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="p-4 w-32 whitespace-nowrap">Mã</th>
                        <th class="p-4">Tên</th>
                        <th class="p-4">Liên hệ</th>
                        <th class="p-4 text-center whitespace-nowrap">Trạng thái</th>
                        <th class="p-4 text-right whitespace-nowrap">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($suppliers as $supplier)
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="p-4 font-mono font-bold text-accent text-xs whitespace-nowrap">{{ $supplier['code'] ?? '' }}</td>
                        <td class="p-4 max-w-[18rem]">
                            <strong class="font-bold text-main text-sm block break-words">{{ $supplier['name'] ?? '' }}</strong>
                            @if ($supplier['address'] ?? null)<span class="block text-[11px] text-muted mt-0.5 break-words">{{ $supplier['address'] }}</span>@endif
                        </td>
                        <td class="p-4">
                            <span class="font-semibold text-main text-xs block whitespace-nowrap">{{ $supplier['phone'] ?? '' }}</span>
                            @if ($supplier['email'] ?? null)<span class="block text-[11px] text-muted break-all">{{ $supplier['email'] }}</span>@endif
                        </td>
                        <td class="p-4 text-center whitespace-nowrap">
                            <div class="inline-flex flex-col items-center gap-1.5">
                                @if ($supplier['is_active'] ?? false)
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                                        Hoạt động
                                    </span>
                                    @if ($supplier['deactivateUrl'] ?? null)
                                        <form action="{{ $supplier['deactivateUrl'] }}" method="POST" @submit.prevent="submitToggle($event, 'supplier-{{ $supplier['id'] ?? $loop->index }}')" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" :disabled="isBusy()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-border bg-surface text-main hover:border-error/50 hover:bg-error-bg hover:text-error transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Ngừng hoạt động</span>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-muted" aria-hidden="true"></span>
                                        Đã ngừng
                                    </span>
                                    @if ($supplier['activateUrl'] ?? null)
                                        <form action="{{ $supplier['activateUrl'] }}" method="POST" @submit.prevent="submitToggle($event, 'supplier-{{ $supplier['id'] ?? $loop->index }}')" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" :disabled="isBusy()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-success/40 bg-success-bg/70 text-success hover:bg-success hover:text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Kích hoạt</span>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="p-4 text-right whitespace-nowrap">
                            @if ($supplier['editUrl'] ?? null)
                                <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors" href="{{ $supplier['editUrl'] }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                    </svg>
                                    <span>Sửa</span>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-12 text-center text-xs text-muted" colspan="5">Chưa có nhà cung cấp.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($paginator)<x-ui.pagination :paginator="$paginator" />@endif
    </div>
</section>
