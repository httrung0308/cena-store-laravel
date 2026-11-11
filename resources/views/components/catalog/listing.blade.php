@props(['paginator', 'filterProps' => [], 'state' => 'ready'])
<section class="mx-auto w-full max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8 py-2 text-main" aria-label="Danh sách sản phẩm">
    <div class="grid gap-8 lg:grid-cols-[17rem_minmax(0,1fr)] items-start">
        <aside><x-catalog.filter-panel :action="$filterProps['action'] ?? null" :reset-url="$filterProps['resetUrl'] ?? null" :filters="$filterProps['filters'] ?? []" :gender-options="$filterProps['genderOptions'] ?? []" :color-options="$filterProps['colorOptions'] ?? []" :category-options="$filterProps['categoryOptions'] ?? []" :silhouette-options="$filterProps['silhouetteOptions'] ?? []" :activity-options="$filterProps['activityOptions'] ?? []" /></aside>
        <div class="space-y-6">
            @if ($state === 'error')
                <x-ui.feedback state="error" />
            @elseif ($state === 'offline')
                <x-ui.feedback state="offline" />
            @elseif (! $paginator || $paginator->total() === 0)
                <x-ui.feedback state="empty" title="Chưa có sản phẩm phù hợp" />
            @else
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted"><span class="text-accent font-bold">{{ $paginator->total() }}</span> sản phẩm</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($paginator->items() as $entry)
                        <x-catalog.product-card :product="$entry['product'] ?? []" :detail-url="$entry['detailUrl'] ?? null" :image-url="$entry['imageUrl'] ?? null" :eager="$loop->index < 3" />
                    @endforeach
                </div>
                <div class="mt-8">
                    <x-ui.pagination :paginator="$paginator" />
                </div>
            @endif
        </div>
    </div>
</section>
