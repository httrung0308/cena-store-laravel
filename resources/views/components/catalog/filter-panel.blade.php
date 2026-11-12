@props([
    'action' => null,
    'resetUrl' => null,
    'filters' => [],
    'genderOptions' => [],
    'categoryOptions' => [],
    'silhouetteOptions' => [],
    'activityOptions' => [],
    'colorOptions' => [],
    'totalCount' => null,
])
@php
    $actionUrl = $action ?? route('shoes.index');
    $clearUrl = $resetUrl ?? $actionUrl;

    $resolvedGenderOptions = ! empty($genderOptions) ? $genderOptions : [
        ['value' => '', 'label' => 'Tất cả'],
        ['value' => 'men', 'label' => 'Nam'],
        ['value' => 'women', 'label' => 'Nữ'],
        ['value' => 'kids', 'label' => 'Trẻ em'],
        ['value' => 'unisex', 'label' => 'Unisex'],
    ];

    $selectedGender = (string) ($filters['gender'] ?? '');
    $selectedCategory = (string) ($filters['category'] ?? '');
    $selectedSilhouettes = array_values(array_filter((array) ($filters['silhouette'] ?? []), 'is_string'));
    $selectedActivities = array_values(array_filter((array) ($filters['activity'] ?? []), 'is_string'));
    $selectedColors = array_values(array_filter((array) ($filters['colors'] ?? []), 'is_string'));

    $rawSize = (string) ($filters['size'] ?? '');
    $sizeValue = ($rawSize !== '' && str_contains($rawSize, '.')) ? rtrim(rtrim($rawSize, '0'), '.') : $rawSize;

    $minPriceValue = ($filters['min_price'] ?? null) !== null ? (string) $filters['min_price'] : '';
    $maxPriceValue = ($filters['max_price'] ?? null) !== null ? (string) $filters['max_price'] : '';
    $isPromotion = ! empty($filters['promotion']);
    $isInStock = ! empty($filters['in_stock']);
    $selectedSort = (string) ($filters['sort'] ?? 'newest');

    $nonRootCategories = collect($categoryOptions)->filter(fn ($opt) => ! ($opt['is_root_gender'] ?? false))->values()->all();
    $showCategoryFilter = ! empty($nonRootCategories) || $selectedCategory !== '';
    $displayedCategoryOptions = ! empty($nonRootCategories) ? $nonRootCategories : $categoryOptions;

    $palette = config('store.palette', []);

    $sortOptions = [
        'newest' => 'Mới nhất',
        'price_asc' => 'Giá: Thấp → Cao',
        'price_desc' => 'Giá: Cao → Thấp',
        'name_asc' => 'Tên: A → Z',
        'name_desc' => 'Tên: Z → A',
    ];
@endphp

<form method="GET"
    action="{{ $actionUrl }}"
    x-ref="form"
    @submit.prevent="submitForm(true)"
    class="flex flex-col gap-3"
    aria-label="Bộ lọc sản phẩm">

    {{-- Row 1: Search, Mobile Filter Trigger, Sort & Result Summary --}}
    <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
        <div class="relative min-w-0 flex-1 sm:max-w-sm">
            <label for="catalog-search-input" class="sr-only">Tìm kiếm sản phẩm</label>
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="8" />
                <path stroke-linecap="round" d="m21 21-4.35-4.35" />
            </svg>
            <input id="catalog-search-input"
                type="search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Tìm giày, dòng sản phẩm..."
                @input="syncCountsFromForm()"
                @keydown.enter.prevent="submitForm(false)"
                class="w-full rounded-xl border border-border bg-surface-card py-2 pl-9 pr-14 text-sm text-main placeholder:text-muted/60 transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
            <div class="absolute right-1.5 top-1/2 flex -translate-y-1/2 items-center gap-1">
                <button type="button"
                    x-cloak
                    x-show="hasSearch"
                    @click="clearSearchAndSubmit()"
                    aria-label="Xóa từ khóa tìm kiếm"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-lg text-muted transition hover:bg-surface hover:text-main focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <button type="submit"
                    aria-label="Tìm kiếm"
                    class="inline-flex h-7 items-center rounded-lg bg-surface px-2 text-xs font-semibold text-main transition hover:bg-accent hover:text-accent-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    Tìm
                </button>
            </div>
        </div>

        {{-- Tablet/Mobile Filter Drawer Button --}}
        <button type="button"
            x-ref="drawerTrigger"
            @click="openDrawer()"
            :aria-expanded="drawerOpen.toString()"
            aria-controls="catalog-facets-container"
            class="inline-flex items-center gap-2 rounded-xl border border-border bg-surface-card px-3.5 py-2 text-xs font-semibold text-main shadow-subtle transition hover:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent lg:hidden">
            <svg class="h-4 w-4 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M6 12h12m-9 7h6" />
            </svg>
            <span>Bộ lọc</span>
            <span x-cloak
                x-show="counts.total > 0"
                x-text="counts.total"
                class="inline-flex min-w-5 items-center justify-center rounded-full bg-accent px-1.5 py-0.5 text-[11px] font-bold leading-none text-accent-text"></span>
        </button>

        {{-- Sort --}}
        <div class="ml-auto flex items-center gap-2">
            <label for="catalog-sort-select" class="hidden text-xs font-semibold text-muted sm:inline">Sắp xếp</label>
            <select id="catalog-sort-select"
                name="sort"
                @change="scheduleSubmit(0)"
                class="rounded-xl border border-border bg-surface-card px-3 py-2 text-xs font-semibold text-main transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                @foreach ($sortOptions as $val => $label)
                    <option value="{{ $val }}" @selected($selectedSort === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Row 2 (Desktop Horizontal Facet Popovers) / Slide-over Drawer (Tablet & Mobile) --}}
    <div id="catalog-facets-container"
        :class="drawerOpen ? 'fixed inset-0 z-50 flex justify-end lg:static lg:inset-auto lg:z-auto lg:block' : 'hidden lg:block'"
        @keydown.escape.window="if (openFacet) { openFacet = null; } else if (drawerOpen) { closeDrawer(); }">

        {{-- Backdrop (< lg only) --}}
        <div class="fixed inset-0 bg-black/55 lg:hidden" @click="closeDrawer()" aria-hidden="true"></div>

        <div role="dialog"
            :aria-modal="drawerOpen ? 'true' : null"
            aria-label="Tất cả bộ lọc"
            class="relative z-10 flex h-full w-full max-w-md flex-col border-l border-border bg-surface-card shadow-2xl lg:static lg:h-auto lg:w-auto lg:max-w-none lg:border-0 lg:bg-transparent lg:shadow-none">

            {{-- Mobile Drawer Header --}}
            <div class="flex items-center justify-between border-b border-border px-5 py-4 lg:hidden">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-main">Bộ lọc sản phẩm</h2>
                    <span x-cloak
                        x-show="counts.total > 0"
                        x-text="counts.total"
                        class="inline-flex min-w-5 items-center justify-center rounded-full bg-accent px-1.5 py-0.5 text-[11px] font-bold leading-none text-accent-text"></span>
                </div>
                <button type="button"
                    x-ref="drawerClose"
                    @click="closeDrawer()"
                    aria-label="Đóng bộ lọc"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border text-muted transition hover:bg-surface hover:text-main focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Facets Body: Scrollable stacked sections on < lg, Horizontal facet pills + popovers on lg+ --}}
            <div class="flex-1 divide-y divide-border overflow-y-auto px-5 py-2 lg:flex lg:flex-none lg:flex-wrap lg:items-center lg:gap-2 lg:divide-y-0 lg:overflow-visible lg:p-0">

                {{-- 1. Đối tượng (Segmented radios) --}}
                <section class="py-4 lg:py-0">
                    <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-muted lg:hidden">Đối tượng</p>
                    <fieldset class="flex flex-wrap gap-1.5 lg:inline-flex lg:items-center lg:gap-1 lg:rounded-xl lg:border lg:border-border lg:bg-surface-card lg:p-1" aria-label="Đối tượng">
                        <legend class="sr-only">Đối tượng</legend>
                        @foreach ($resolvedGenderOptions as $opt)
                            <label class="cursor-pointer">
                                <input type="radio" name="gender" value="{{ $opt['value'] }}" @checked($selectedGender === $opt['value']) @change="onControlAutoChange(0)" class="peer sr-only">
                                <span class="inline-flex items-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-muted transition hover:text-main peer-checked:border-accent peer-checked:bg-accent peer-checked:text-accent-text peer-focus-visible:ring-2 peer-focus-visible:ring-accent lg:border-0 lg:bg-transparent lg:px-2.5 lg:py-1">
                                    {{ $opt['label'] }}
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                </section>

                {{-- 2. Kiểu dáng --}}
                @if (! empty($silhouetteOptions))
                    <section class="py-4 lg:relative lg:py-0" @click.outside="if (openFacet === 'silhouette') openFacet = null">
                        <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-muted lg:hidden">Kiểu dáng</p>
                        <button type="button"
                            @click="toggleFacet('silhouette')"
                            :aria-expanded="(openFacet === 'silhouette').toString()"
                            aria-controls="facet-panel-silhouette"
                            class="hidden items-center gap-1.5 rounded-xl border border-border bg-surface-card px-3 py-2 text-xs font-semibold text-main transition hover:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent lg:inline-flex"
                            :class="openFacet === 'silhouette' || counts.silhouette > 0 ? 'border-accent' : ''">
                            <span>Kiểu dáng</span>
                            <span x-cloak
                                x-show="counts.silhouette > 0"
                                x-text="counts.silhouette"
                                class="inline-flex min-w-4 items-center justify-center rounded-full bg-accent px-1.5 py-0.5 text-[10px] font-bold leading-none text-accent-text"></span>
                            <svg class="h-3.5 w-3.5 text-muted transition-transform" :class="openFacet === 'silhouette' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                            </svg>
                        </button>

                        <div id="facet-panel-silhouette"
                            :class="openFacet === 'silhouette' ? 'lg:block' : 'lg:hidden'"
                            class="lg:absolute lg:left-0 lg:top-full lg:z-30 lg:mt-2 lg:w-80 lg:rounded-2xl lg:border lg:border-border lg:bg-surface-card lg:p-3.5 lg:shadow-xl">
                            @if (count($silhouetteOptions) >= 6)
                                <div class="mb-2.5">
                                    <label for="facet-search-silhouette" class="sr-only">Tìm nhanh kiểu dáng</label>
                                    <input id="facet-search-silhouette"
                                        type="search"
                                        x-model="facetSearch.silhouette"
                                        placeholder="Lọc nhanh kiểu dáng..."
                                        class="w-full rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-main placeholder:text-muted/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                                </div>
                            @endif
                            <div class="max-h-60 space-y-1 overflow-y-auto pr-1">
                                @foreach ($silhouetteOptions as $opt)
                                    <label x-show="matchesFacetSearch('silhouette', @js($opt['label']))"
                                        class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-xs text-main transition hover:bg-surface">
                                        <input type="checkbox" name="silhouette[]" value="{{ $opt['value'] }}" @checked(in_array($opt['value'], $selectedSilhouettes, true)) @change="onControlAutoChange(180)" class="native-checkbox mt-0.5 h-4 w-4 shrink-0 rounded border-border accent-accent">
                                        <span class="min-w-0 whitespace-normal break-words leading-snug">{{ $opt['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                {{-- 3. Mục đích sử dụng --}}
                @if (! empty($activityOptions))
                    <section class="py-4 lg:relative lg:py-0" @click.outside="if (openFacet === 'activity') openFacet = null">
                        <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-muted lg:hidden">Mục đích sử dụng</p>
                        <button type="button"
                            @click="toggleFacet('activity')"
                            :aria-expanded="(openFacet === 'activity').toString()"
                            aria-controls="facet-panel-activity"
                            class="hidden items-center gap-1.5 rounded-xl border border-border bg-surface-card px-3 py-2 text-xs font-semibold text-main transition hover:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent lg:inline-flex"
                            :class="openFacet === 'activity' || counts.activity > 0 ? 'border-accent' : ''">
                            <span>Mục đích sử dụng</span>
                            <span x-cloak
                                x-show="counts.activity > 0"
                                x-text="counts.activity"
                                class="inline-flex min-w-4 items-center justify-center rounded-full bg-accent px-1.5 py-0.5 text-[10px] font-bold leading-none text-accent-text"></span>
                            <svg class="h-3.5 w-3.5 text-muted transition-transform" :class="openFacet === 'activity' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                            </svg>
                        </button>

                        <div id="facet-panel-activity"
                            :class="openFacet === 'activity' ? 'lg:block' : 'lg:hidden'"
                            class="lg:absolute lg:left-0 lg:top-full lg:z-30 lg:mt-2 lg:w-80 lg:rounded-2xl lg:border lg:border-border lg:bg-surface-card lg:p-3.5 lg:shadow-xl">
                            @if (count($activityOptions) >= 6)
                                <div class="mb-2.5">
                                    <label for="facet-search-activity" class="sr-only">Tìm nhanh mục đích sử dụng</label>
                                    <input id="facet-search-activity"
                                        type="search"
                                        x-model="facetSearch.activity"
                                        placeholder="Lọc nhanh mục đích..."
                                        class="w-full rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-main placeholder:text-muted/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                                </div>
                            @endif
                            <div class="max-h-60 space-y-1 overflow-y-auto pr-1">
                                @foreach ($activityOptions as $opt)
                                    <label x-show="matchesFacetSearch('activity', @js($opt['label']))"
                                        class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-xs text-main transition hover:bg-surface">
                                        <input type="checkbox" name="activity[]" value="{{ $opt['value'] }}" @checked(in_array($opt['value'], $selectedActivities, true)) @change="onControlAutoChange(180)" class="native-checkbox mt-0.5 h-4 w-4 shrink-0 rounded border-border accent-accent">
                                        <span class="min-w-0 whitespace-normal break-words leading-snug">{{ $opt['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                {{-- 4. Màu sắc --}}
                @if (! empty($colorOptions))
                    <section class="py-4 lg:relative lg:py-0" @click.outside="if (openFacet === 'colors') openFacet = null">
                        <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-muted lg:hidden">Màu sắc</p>
                        <button type="button"
                            @click="toggleFacet('colors')"
                            :aria-expanded="(openFacet === 'colors').toString()"
                            aria-controls="facet-panel-colors"
                            class="hidden items-center gap-1.5 rounded-xl border border-border bg-surface-card px-3 py-2 text-xs font-semibold text-main transition hover:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent lg:inline-flex"
                            :class="openFacet === 'colors' || counts.colors > 0 ? 'border-accent' : ''">
                            <span>Màu sắc</span>
                            <span x-cloak
                                x-show="counts.colors > 0"
                                x-text="counts.colors"
                                class="inline-flex min-w-4 items-center justify-center rounded-full bg-accent px-1.5 py-0.5 text-[10px] font-bold leading-none text-accent-text"></span>
                            <svg class="h-3.5 w-3.5 text-muted transition-transform" :class="openFacet === 'colors' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                            </svg>
                        </button>

                        <div id="facet-panel-colors"
                            :class="openFacet === 'colors' ? 'lg:block' : 'lg:hidden'"
                            class="lg:absolute lg:left-0 lg:top-full lg:z-30 lg:mt-2 lg:w-80 lg:rounded-2xl lg:border lg:border-border lg:bg-surface-card lg:p-3.5 lg:shadow-xl">
                            <div class="grid grid-cols-2 gap-1.5">
                                @foreach ($colorOptions as $color)
                                    @php
                                        $colorHex = $color['hex'] ?? ($palette[$color['value']]['hex'] ?? '#64748b');
                                    @endphp
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-main transition hover:bg-surface">
                                        <input type="checkbox" name="colors[]" value="{{ $color['value'] }}" @checked(in_array($color['value'], $selectedColors, true)) @change="onControlAutoChange(180)" class="native-checkbox h-4 w-4 shrink-0 rounded border-border accent-accent">
                                        <span class="h-3 w-3 shrink-0 rounded-full border border-border" style="background-color: {{ $colorHex }}"></span>
                                        <span class="whitespace-normal leading-snug">{{ $color['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                {{-- 5. Size & Khoảng giá --}}
                <section class="py-4 lg:relative lg:py-0" @click.outside="if (openFacet === 'size_price') openFacet = null">
                    <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-muted lg:hidden">Size &amp; Khoảng giá</p>
                    <button type="button"
                        @click="toggleFacet('size_price')"
                        :aria-expanded="(openFacet === 'size_price').toString()"
                        aria-controls="facet-panel-size-price"
                        class="hidden items-center gap-1.5 rounded-xl border border-border bg-surface-card px-3 py-2 text-xs font-semibold text-main transition hover:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent lg:inline-flex"
                        :class="openFacet === 'size_price' || counts.sizePrice > 0 ? 'border-accent' : ''">
                        <span>Size &amp; Giá</span>
                        <span x-cloak
                            x-show="counts.sizePrice > 0"
                            x-text="counts.sizePrice"
                            class="inline-flex min-w-4 items-center justify-center rounded-full bg-accent px-1.5 py-0.5 text-[10px] font-bold leading-none text-accent-text"></span>
                        <svg class="h-3.5 w-3.5 text-muted transition-transform" :class="openFacet === 'size_price' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="facet-panel-size-price"
                        :class="openFacet === 'size_price' ? 'lg:block' : 'lg:hidden'"
                        class="space-y-3.5 lg:absolute lg:left-0 lg:top-full lg:z-30 lg:mt-2 lg:w-80 lg:rounded-2xl lg:border lg:border-border lg:bg-surface-card lg:p-4 lg:shadow-xl">
                        <div>
                            <label for="catalog-size-input" class="mb-1.5 block text-xs font-semibold text-main">Size giày (VN/EU)</label>
                            <input id="catalog-size-input"
                                type="text"
                                name="size"
                                inputmode="decimal"
                                value="{{ $sizeValue }}"
                                placeholder="Ví dụ: 40, 40.5"
                                @input="syncCountsFromForm()"
                                @keydown.enter.prevent="openFacet = null; submitForm(false)"
                                class="w-full rounded-lg border border-border bg-surface-card px-3 py-1.5 text-xs text-main placeholder:text-muted/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach (['38', '39', '40', '40.5', '41', '42', '43'] as $presetSize)
                                    <button type="button"
                                        @click="$refs.form.elements.namedItem('size').value = ($refs.form.elements.namedItem('size').value === '{{ $presetSize }}' ? '' : '{{ $presetSize }}'); syncCountsFromForm();"
                                        class="rounded-lg border border-border bg-surface px-2 py-1 text-[11px] font-semibold text-main transition hover:border-accent">
                                        {{ $presetSize }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label for="catalog-min-price-input" class="mb-1 block text-xs font-semibold text-main">Giá từ (₫)</label>
                                <input id="catalog-min-price-input"
                                    type="number"
                                    name="min_price"
                                    min="0"
                                    step="10000"
                                    value="{{ $minPriceValue }}"
                                    placeholder="0"
                                    @input="syncCountsFromForm()"
                                    @keydown.enter.prevent="openFacet = null; submitForm(false)"
                                    class="w-full rounded-lg border border-border bg-surface-card px-2.5 py-1.5 text-xs text-main placeholder:text-muted/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                            </div>
                            <div>
                                <label for="catalog-max-price-input" class="mb-1 block text-xs font-semibold text-main">Giá đến (₫)</label>
                                <input id="catalog-max-price-input"
                                    type="number"
                                    name="max_price"
                                    min="0"
                                    step="10000"
                                    value="{{ $maxPriceValue }}"
                                    placeholder="5.000.000"
                                    @input="syncCountsFromForm()"
                                    @keydown.enter.prevent="openFacet = null; submitForm(false)"
                                    class="w-full rounded-lg border border-border bg-surface-card px-2.5 py-1.5 text-xs text-main placeholder:text-muted/60 focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                            </div>
                        </div>

                        <div class="hidden items-center justify-between gap-2 border-t border-border pt-3 lg:flex">
                            <button type="button"
                                @click="clearSizePriceAndSubmit()"
                                class="text-xs font-semibold text-muted transition hover:text-main">
                                Xóa size &amp; giá
                            </button>
                            <button type="button"
                                @click="openFacet = null; submitForm(false)"
                                class="rounded-lg bg-accent px-3.5 py-1.5 text-xs font-semibold text-accent-text transition hover:bg-accent-hover">
                                Áp dụng
                            </button>
                        </div>
                    </div>
                </section>

                {{-- 6. Tình trạng nhanh (Khuyến mãi & Còn hàng) --}}
                <section class="py-4 lg:py-0">
                    <p class="mb-2.5 text-xs font-bold uppercase tracking-wider text-muted lg:hidden">Tình trạng</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="cursor-pointer">
                            <input type="checkbox"
                                name="promotion"
                                value="1"
                                @checked($isPromotion)
                                @change="onControlAutoChange(0)"
                                class="peer sr-only">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-surface-card px-3 py-2 text-xs font-semibold text-main transition hover:border-accent peer-checked:border-accent peer-checked:bg-accent peer-checked:text-accent-text peer-focus-visible:ring-2 peer-focus-visible:ring-accent">
                                Đang giảm giá
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="checkbox"
                                name="in_stock"
                                value="1"
                                @checked($isInStock)
                                @change="onControlAutoChange(0)"
                                class="peer sr-only">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-surface-card px-3 py-2 text-xs font-semibold text-main transition hover:border-accent peer-checked:border-accent peer-checked:bg-accent peer-checked:text-accent-text peer-focus-visible:ring-2 peer-focus-visible:ring-accent">
                                Chỉ còn hàng
                            </span>
                        </label>
                    </div>
                </section>

                {{-- 7. Danh mục chi tiết (chỉ hiện khi có danh mục con hoặc đang lọc category trên URL) --}}
                @if ($showCategoryFilter)
                    <section class="py-4 lg:py-0">
                        <label for="catalog-category-select" class="mb-2 block text-xs font-bold uppercase tracking-wider text-muted lg:sr-only">Danh mục chi tiết</label>
                        <select id="catalog-category-select"
                            name="category"
                            @change="onControlAutoChange(0)"
                            class="w-full rounded-xl border border-border bg-surface-card px-3 py-2 text-xs font-semibold text-main transition hover:border-accent focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 lg:w-auto">
                            <option value="">Tất cả danh mục chi tiết</option>
                            @foreach ($displayedCategoryOptions as $cat)
                                <option value="{{ $cat['slug'] }}" @selected($selectedCategory === $cat['slug'])>{{ $cat['name'] }}</option>
                            @endforeach
                        </select>
                    </section>
                @endif

                {{-- Desktop Reset Link when any filter is active --}}
                <div x-cloak x-show="counts.total > 0" class="hidden lg:ml-auto lg:block">
                    <a href="{{ $clearUrl }}"
                        data-filter-reset
                        @click.prevent="navigateUrl($el.getAttribute('href'))"
                        class="inline-flex items-center gap-1 rounded-xl px-2.5 py-2 text-xs font-semibold text-muted transition hover:bg-surface hover:text-main focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                        Xóa lọc
                    </a>
                </div>
            </div>

            {{-- Mobile/Tablet Sticky Drawer Footer --}}
            <div class="sticky bottom-0 flex items-center gap-3 border-t border-border bg-surface-card px-5 py-3.5 lg:hidden">
                <a href="{{ $clearUrl }}"
                    data-filter-reset
                    @click.prevent="navigateUrl($el.getAttribute('href'))"
                    class="inline-flex flex-1 items-center justify-center rounded-xl border border-border px-4 py-2.5 text-xs font-bold text-main transition hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    Xóa tất cả
                </a>
                <button type="submit"
                    class="inline-flex flex-[1.4] items-center justify-center rounded-xl bg-accent px-4 py-2.5 text-xs font-bold text-accent-text transition hover:bg-accent-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    Áp dụng bộ lọc
                </button>
            </div>
        </div>
    </div>

    <noscript>
        <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="rounded-xl bg-accent px-4 py-2 text-xs font-bold text-accent-text">Áp dụng bộ lọc</button>
            <a href="{{ $clearUrl }}" class="text-xs font-semibold text-muted underline">Xóa toàn bộ</a>
        </div>
    </noscript>
</form>
