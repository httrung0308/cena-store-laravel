@props([
    'action' => null,
    'resetUrl' => null,
    'filters' => [],
    'genderOptions' => [],
    'categoryOptions' => [],
    'silhouetteOptions' => [],
    'activityOptions' => [],
    'colorOptions' => [],
])
@php
    $actionUrl = $action ?? route('shoes.index');
    $clearUrl = $resetUrl ?? $actionUrl;

    $genderMap = collect($genderOptions)->pluck('label', 'value')->all();
    $categoryMap = collect($categoryOptions)->mapWithKeys(fn ($c) => [$c['slug'] => ltrim((string) ($c['name'] ?? $c['slug']), '— ')])->all();
    $silhouetteMap = collect($silhouetteOptions)->pluck('label', 'value')->all();
    $activityMap = collect($activityOptions)->pluck('label', 'value')->all();
    $colorMap = collect($colorOptions)->keyBy('value')->all();
    $paletteFallback = config('store.palette', []);

    $baseQuery = [
        'search' => ($filters['search'] ?? '') !== '' ? (string) $filters['search'] : null,
        'gender' => ($filters['gender'] ?? '') !== '' ? (string) $filters['gender'] : null,
        'category' => ($filters['category'] ?? '') !== '' ? (string) $filters['category'] : null,
        'silhouette' => array_values(array_filter((array) ($filters['silhouette'] ?? []), fn ($v) => is_string($v) && $v !== '')),
        'activity' => array_values(array_filter((array) ($filters['activity'] ?? []), fn ($v) => is_string($v) && $v !== '')),
        'colors' => array_values(array_filter((array) ($filters['colors'] ?? []), fn ($v) => is_string($v) && $v !== '')),
        'size' => ($filters['size'] ?? '') !== '' ? (string) $filters['size'] : null,
        'min_price' => $filters['min_price'] ?? null,
        'max_price' => $filters['max_price'] ?? null,
        'promotion' => ! empty($filters['promotion']) ? '1' : null,
        'in_stock' => ! empty($filters['in_stock']) ? '1' : null,
        'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? (string) $filters['sort'] : null,
    ];

    $buildWithout = function (string $key, ?string $arrayValue = null) use ($actionUrl, $baseQuery): string {
        $next = $baseQuery;
        if ($arrayValue !== null) {
            $next[$key] = array_values(array_filter((array) ($next[$key] ?? []), fn ($v) => $v !== $arrayValue));
        } else {
            $next[$key] = null;
        }
        $filtered = array_filter($next, fn ($v) => $v !== null && $v !== '' && $v !== []);
        $qs = http_build_query($filtered);

        return $qs !== '' ? "{$actionUrl}?{$qs}" : $actionUrl;
    };

    $chips = [];

    if ($baseQuery['search'] !== null) {
        $chips[] = [
            'label' => 'Từ khóa: “'.$baseQuery['search'].'”',
            'url' => $buildWithout('search'),
            'hex' => null,
        ];
    }

    if ($baseQuery['gender'] !== null) {
        $label = $genderMap[$baseQuery['gender']] ?? ucfirst($baseQuery['gender']);
        $chips[] = [
            'label' => 'Đối tượng: '.$label,
            'url' => $buildWithout('gender'),
            'hex' => null,
        ];
    }

    if ($baseQuery['category'] !== null) {
        $label = $categoryMap[$baseQuery['category']] ?? $baseQuery['category'];
        $chips[] = [
            'label' => 'Danh mục: '.$label,
            'url' => $buildWithout('category'),
            'hex' => null,
        ];
    }

    foreach ($baseQuery['silhouette'] as $slug) {
        $chips[] = [
            'label' => 'Kiểu dáng: '.($silhouetteMap[$slug] ?? $slug),
            'url' => $buildWithout('silhouette', $slug),
            'hex' => null,
        ];
    }

    foreach ($baseQuery['activity'] as $slug) {
        $chips[] = [
            'label' => 'Mục đích: '.($activityMap[$slug] ?? $slug),
            'url' => $buildWithout('activity', $slug),
            'hex' => null,
        ];
    }

    foreach ($baseQuery['colors'] as $colorKey) {
        $option = $colorMap[$colorKey] ?? null;
        $label = is_array($option) ? ($option['label'] ?? $colorKey) : $colorKey;
        $hex = is_array($option) ? ($option['hex'] ?? ($paletteFallback[$colorKey]['hex'] ?? null)) : ($paletteFallback[$colorKey]['hex'] ?? null);
        $chips[] = [
            'label' => 'Màu: '.$label,
            'url' => $buildWithout('colors', $colorKey),
            'hex' => $hex,
        ];
    }

    if ($baseQuery['size'] !== null) {
        $rawSize = $baseQuery['size'];
        $displaySize = str_contains($rawSize, '.') ? rtrim(rtrim($rawSize, '0'), '.') : $rawSize;
        $chips[] = [
            'label' => 'Size: '.$displaySize,
            'url' => $buildWithout('size'),
            'hex' => null,
        ];
    }

    if ($baseQuery['min_price'] !== null) {
        $chips[] = [
            'label' => 'Giá từ: '.number_format((int) $baseQuery['min_price'], 0, ',', '.').' ₫',
            'url' => $buildWithout('min_price'),
            'hex' => null,
        ];
    }

    if ($baseQuery['max_price'] !== null) {
        $chips[] = [
            'label' => 'Giá đến: '.number_format((int) $baseQuery['max_price'], 0, ',', '.').' ₫',
            'url' => $buildWithout('max_price'),
            'hex' => null,
        ];
    }

    if ($baseQuery['promotion'] !== null) {
        $chips[] = [
            'label' => 'Đang giảm giá',
            'url' => $buildWithout('promotion'),
            'hex' => null,
        ];
    }

    if ($baseQuery['in_stock'] !== null) {
        $chips[] = [
            'label' => 'Chỉ còn hàng',
            'url' => $buildWithout('in_stock'),
            'hex' => null,
        ];
    }
@endphp
@if (count($chips) > 0)
    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-surface/60 px-3.5 py-2.5" aria-label="Điều kiện đang lọc">
        <span class="mr-1 text-xs font-semibold text-muted">Đang lọc:</span>
        @foreach ($chips as $chip)
            <a href="{{ $chip['url'] }}"
                data-chip-remove
                @click.prevent="navigateUrl($el.getAttribute('href'))"
                class="group inline-flex items-center gap-1.5 rounded-full border border-border bg-surface-card px-2.5 py-1 text-xs font-medium text-main shadow-subtle transition hover:border-accent hover:text-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                @if ($chip['hex'])
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full border border-border" style="background-color: {{ $chip['hex'] }}"></span>
                @endif
                <span>{{ $chip['label'] }}</span>
                <span class="ml-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full text-muted transition group-hover:bg-accent/10 group-hover:text-accent" aria-hidden="true">×</span>
                <span class="sr-only">(bỏ điều kiện {{ $chip['label'] }})</span>
            </a>
        @endforeach
        <a href="{{ $clearUrl }}"
            data-filter-reset
            @click.prevent="navigateUrl($el.getAttribute('href'))"
            class="ml-auto inline-flex items-center gap-1 rounded-full border border-border bg-surface-card px-3 py-1 text-xs font-semibold text-main transition hover:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
            Xóa tất cả
        </a>
    </div>
@endif
