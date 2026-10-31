@props(['paginator', 'filterProps' => [], 'state' => 'ready'])
<section class="mx-auto w-full max-w-7xl space-y-6 px-4 py-8 text-black" aria-label="Danh sách sản phẩm">
    <div class="grid gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside><x-catalog.filter-panel :action="$filterProps['action'] ?? null" :reset-url="$filterProps['resetUrl'] ?? null" :filters="$filterProps['filters'] ?? []" :gender-options="$filterProps['genderOptions'] ?? []" :color-options="$filterProps['colorOptions'] ?? []" :category-options="$filterProps['categoryOptions'] ?? []" /></aside>
        <div class="space-y-5">
            @if ($state === 'error')
                <x-ui.feedback state="error" />
            @elseif ($state === 'offline')
                <x-ui.feedback state="offline" />
            @elseif (! $paginator || $paginator->total() === 0)
                <x-ui.feedback state="empty" title="Chưa có sản phẩm phù hợp" />
            @else
                <p class="text-sm text-zinc-600">{{ $paginator->total() }} sản phẩm</p>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($paginator->items() as $entry)
                        <x-catalog.product-card :product="$entry['product'] ?? []" :detail-url="$entry['detailUrl'] ?? null" :image-url="$entry['imageUrl'] ?? null" />
                    @endforeach
                </div>
                <x-ui.pagination :paginator="$paginator" />
            @endif
        </div>
    </div>
</section>
