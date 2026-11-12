@props(['paginator', 'filterProps' => [], 'state' => 'ready'])
@php
    $filters = $filterProps['filters'] ?? [];
    $actionUrl = $filterProps['action'] ?? route('shoes.index');
    $resetUrl = $filterProps['resetUrl'] ?? $actionUrl;
    $genderOptions = $filterProps['genderOptions'] ?? [];
    $categoryOptions = $filterProps['categoryOptions'] ?? [];
    $silhouetteOptions = $filterProps['silhouetteOptions'] ?? [];
    $activityOptions = $filterProps['activityOptions'] ?? [];
    $colorOptions = $filterProps['colorOptions'] ?? [];
@endphp
<section id="catalog-listing-root"
    x-ref="root"
    x-data="catalogListing({ baseUrl: @js($actionUrl) })"
    class="scroll-mt-20 mx-auto w-full max-w-7xl space-y-5 px-4 py-2 text-main sm:px-6 lg:px-8"
    aria-label="Danh sách sản phẩm">

    <x-catalog.filter-panel
        :action="$actionUrl"
        :reset-url="$resetUrl"
        :filters="$filters"
        :gender-options="$genderOptions"
        :category-options="$categoryOptions"
        :silhouette-options="$silhouetteOptions"
        :activity-options="$activityOptions"
        :color-options="$colorOptions"
        :total-count="$paginator?->total()" />

    <div id="catalog-chips-region" x-ref="chipsRegion">
        <x-catalog.active-chips
            :action="$actionUrl"
            :reset-url="$resetUrl"
            :filters="$filters"
            :gender-options="$genderOptions"
            :category-options="$categoryOptions"
            :silhouette-options="$silhouetteOptions"
            :activity-options="$activityOptions"
            :color-options="$colorOptions" />
    </div>

    <div x-cloak
        x-show="errorMessage !== ''"
        role="alert"
        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-error bg-error-bg px-4 py-3 text-xs font-medium text-error-text">
        <span x-text="errorMessage"></span>
        <div class="flex items-center gap-2">
            <button type="button"
                x-show="lastFailedUrl"
                @click="retryLastRequest()"
                class="rounded-lg border border-error/40 bg-surface-card px-2.5 py-1 font-bold text-main transition hover:bg-surface">
                Thử lại
            </button>
            <button type="button"
                @click="errorMessage = ''"
                aria-label="Đóng thông báo lỗi"
                class="rounded-lg px-2 py-1 font-semibold hover:bg-surface-card/60">
                Đóng
            </button>
        </div>
    </div>

    <div class="relative" :aria-busy="loading ? 'true' : 'false'">
        <div x-cloak
            x-show="loading"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 z-20 flex items-start justify-center rounded-2xl bg-bg/60 pt-16 transition-opacity">
            <div class="inline-flex items-center gap-2.5 rounded-full border border-border bg-surface-card px-4 py-2 text-xs font-semibold text-main shadow-md">
                <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-accent border-t-transparent"></span>
                <span>Đang cập nhật sản phẩm...</span>
            </div>
        </div>

        <div id="catalog-results-region"
            x-ref="resultsRegion"
            @click="handleResultsClick($event)"
            class="space-y-6 transition-opacity duration-150"
            :class="loading ? 'opacity-60' : 'opacity-100'">
            @if ($state === 'error')
                <x-ui.feedback state="error" />
            @elseif ($state === 'offline')
                <x-ui.feedback state="offline" />
            @elseif (! $paginator || $paginator->total() === 0)
                <x-ui.feedback state="empty"
                    title="Chưa có sản phẩm phù hợp"
                    message="Thử bỏ bớt điều kiện lọc hoặc quay lại toàn bộ danh mục sản phẩm.">
                    <x-slot:retry>
                        <a href="{{ $resetUrl }}"
                            data-filter-reset
                            class="inline-flex items-center rounded-xl bg-accent px-4 py-2 text-xs font-bold text-accent-text transition hover:bg-accent-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            Xóa toàn bộ bộ lọc
                        </a>
                    </x-slot:retry>
                </x-ui.feedback>
            @else
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-muted">
                        Hiển thị <span class="font-bold text-main">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                        trong <span class="font-bold text-main">{{ number_format($paginator->total(), 0, ',', '.') }}</span> sản phẩm
                    </p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($paginator->items() as $entry)
                        <x-catalog.product-card
                            :product="$entry['product'] ?? []"
                            :detail-url="$entry['detailUrl'] ?? null"
                            :image-url="$entry['imageUrl'] ?? null"
                            :eager="$loop->index < 3" />
                    @endforeach
                </div>
                <div class="mt-8">
                    <x-ui.pagination :paginator="$paginator" />
                </div>
            @endif
        </div>
    </div>
</section>
