@extends('layouts.admin')
@section('title', 'Danh Sách Sản Phẩm')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" x-data x-on:click="$dispatch('toggle-import-panel')"
                class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors shadow-sm">
            Nhập Excel / CSV
        </button>
        <a class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md" href="{{ route('admin.products.create') }}">
            + Thêm sản phẩm
        </a>
    </div>
@endsection
@php($images = app(App\Services\CatalogQueryService::class))
@section('content')
    <div x-data="{
        ...adminStatusToggle(),
        showImport: @js(session()->has('import_preview') || $errors->has('import_errors')),
        toggleImport() { this.showImport = !this.showImport; }
    }"
    x-on:toggle-import-panel.window="toggleImport()"
    class="space-y-6">

        {{-- Excel / CSV Import Section --}}
        <div x-show="showImport" x-transition class="admin-card space-y-4 border-accent/40 bg-surface/80">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-main">Nhập sản phẩm hàng loạt (Excel / CSV)</h2>
                    <p class="text-xs text-muted mt-0.5">Nhập thông tin sản phẩm, danh mục, màu sắc và dải size. Ảnh sản phẩm sẽ được bổ sung sau trên giao diện chỉnh sửa.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.products.template', ['format' => 'xlsx']) }}"
                       class="px-3 py-1.5 bg-surface hover:bg-border/40 text-accent font-bold text-xs rounded-lg border border-border transition-colors">
                        Tải file mẫu (.xlsx)
                    </a>
                    <a href="{{ route('admin.products.template', ['format' => 'csv']) }}"
                       class="px-3 py-1.5 bg-surface hover:bg-border/40 text-muted hover:text-main font-bold text-xs rounded-lg border border-border transition-colors">
                        Tải file mẫu (.csv)
                    </a>
                    <button type="button" x-on:click="showImport = false" class="text-muted hover:text-main text-xs font-semibold px-2 py-1 ml-1" aria-label="Đóng khung nhập file">Đóng</button>
                </div>
            </div>

            @if ($errors->has('import_errors'))
                <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-xs font-semibold text-error space-y-2">
                    <p class="font-bold">Chưa thể import. Vui lòng kiểm tra và sửa các dòng sau trong file:</p>
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->get('import_errors') as $errorList)
                            @foreach ((array) $errorList as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Upload Form --}}
            <form method="POST" action="{{ route('admin.products.import.preview') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3 pt-1">
                @csrf
                <label for="product-spreadsheet-file" class="sr-only">File Excel hoặc CSV</label>
                <input id="product-spreadsheet-file" type="file" name="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                       class="block w-full max-w-md text-xs text-main file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-surface file:text-main file:border file:border-border hover:file:bg-border/40 cursor-pointer">
                <button type="submit" class="px-5 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md">
                    Kiểm tra file (Xem trước)
                </button>
            </form>

            {{-- Preview section if uploaded successfully --}}
            @if (session()->has('import_preview'))
                @php($previewProducts = session('import_preview'))
                <div class="mt-4 pt-4 border-t border-border/70 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-xs font-bold text-success uppercase">Kết quả kiểm tra file: {{ count($previewProducts) }} sản phẩm hợp lệ</h3>
                            <p class="text-[11px] text-muted">Sản phẩm import chưa có ảnh nên sẽ ở trạng thái <strong class="text-error">Ngừng bán</strong> để đảm bảo không hiển thị thiếu ảnh trên Storefront. Sau khi lưu, bạn bấm "Sửa" để tải ảnh theo màu và mở bán.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.products.import.store') }}">
                            @csrf
                            <input type="hidden" name="products_json" value="{{ json_encode($previewProducts) }}">
                            <button type="submit" class="px-6 py-2 bg-success hover:bg-success/90 text-white text-xs font-bold rounded-xl transition-colors shadow-md">
                                Xác nhận lưu {{ count($previewProducts) }} sản phẩm vào kho
                            </button>
                        </form>
                    </div>

                    <div class="max-h-60 overflow-y-auto border border-border/60 rounded-xl">
                        <table class="w-full text-left text-xs text-muted border-collapse">
                            <thead class="bg-surface text-main font-bold border-b border-border sticky top-0">
                                <tr>
                                    <th class="p-3">Tên sản phẩm</th>
                                    <th class="p-3">Danh mục</th>
                                    <th class="p-3">Giá gốc</th>
                                    <th class="p-3">Các màu</th>
                                    <th class="p-3 text-center">Tổng biến thể</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-surface/40">
                                @foreach ($previewProducts as $p)
                                    <tr>
                                        <td class="p-3 font-bold text-main">{{ $p['name'] }}</td>
                                        <td class="p-3">{{ $p['category_name'] }}</td>
                                        <td class="p-3 font-mono font-bold text-main whitespace-nowrap">{{ number_format($p['base_price'], 0, ',', '.') }} ₫</td>
                                        <td class="p-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($p['colors'] as $col)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-[11px]">
                                                        <span class="w-2.5 h-2.5 rounded-full inline-block border border-border/50" style="background-color: {{ $col['primary_hex'] }}"></span>
                                                        <span>{{ $col['color_name'] }} ({{ count($col['variants']) }} size)</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="p-3 text-center font-bold text-main">{{ $p['total_variants'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <x-admin.search-form :action="route('admin.products.index')" id="product-search" label="Tìm sản phẩm" placeholder="Tìm kiếm theo tên sản phẩm, mã slug..." :value="request('search', '')" />

        <x-admin.ajax-feedback />

        <div id="admin-list-region" x-ref="region" class="bg-surface-card rounded-2xl border border-border overflow-hidden shadow-sm">
        {{-- Mobile card layout (< md) --}}
        <div class="md:hidden divide-y divide-border">
            @forelse ($products as $product)
                @php($thumbnail = $product->colors->first()?->thumbnail)
                @php($stock = (int) ($product->total_stock ?? 0))
                <article class="p-4 space-y-3">
                    <div class="flex items-start gap-3">
                        <a href="{{ route('admin.products.show', $product) }}" class="block w-14 h-14 shrink-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            @if ($thumbnail)
                                <img src="{{ $thumbnail->thumb_64_url ?? $thumbnail->thumbnail_url ?? $images->imageUrl($thumbnail) }}" alt="{{ $product->name }}" class="w-14 h-14 object-cover rounded-xl border border-border bg-surface" loading="lazy" decoding="async" width="56" height="56">
                            @else
                                <div class="flex w-14 h-14 items-center justify-center rounded-xl border border-border bg-surface text-[9px] text-muted text-center px-1 leading-tight">Chưa có ảnh</div>
                            @endif
                        </a>
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('admin.products.show', $product) }}" class="font-bold text-main hover:text-accent hover:underline text-sm leading-snug break-words transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                    {{ $product->name }}
                                </a>
                                @if ($product->is_active)
                                    <span class="shrink-0 inline-flex items-center whitespace-nowrap bg-success-bg text-success border border-success/30 px-2 py-0.5 rounded-full font-bold text-[11px]">Đang bán</span>
                                @else
                                    <span class="shrink-0 inline-flex items-center whitespace-nowrap bg-muted/15 text-muted border border-border px-2 py-0.5 rounded-full font-bold text-[11px]">Đã ẩn</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px]">
                                <span class="text-muted font-mono truncate max-w-[12rem]">{{ $product->slug }}</span>
                                <a href="{{ route('admin.products.show', $product) }}" class="font-semibold text-muted hover:text-accent underline decoration-border hover:decoration-accent underline-offset-2 whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                    Chi tiết
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs pt-1">
                        <div class="font-mono tabular-nums">
                            @if ($product->promotion_price !== null && $product->promotion_price < $product->base_price)
                                <span class="font-extrabold text-accent text-sm">{{ number_format($product->promotion_price, 0, ',', '.') }} ₫</span>
                                <span class="text-[11px] text-muted line-through ml-1.5">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @else
                                <span class="font-extrabold text-main text-sm">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-semibold text-muted">{{ $product->category?->name ?? '—' }}</span>
                            <span @class([
                                'inline-flex items-center whitespace-nowrap border px-2 py-0.5 rounded-lg font-mono tabular-nums font-bold text-[11px]',
                                'bg-surface text-main border-border' => $stock > 0,
                                'bg-error-bg text-error border-error/30' => $stock <= 0,
                            ])>{{ $stock }} đôi</span>
                            <span class="inline-flex items-center whitespace-nowrap bg-surface text-muted border border-border px-2 py-0.5 rounded text-[11px] font-mono tabular-nums">{{ $product->variants_count }} biến thể</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-border/60">
                        @if ($product->is_active)
                            <form method="POST" action="{{ route('admin.products.deactivate', $product) }}" @submit.prevent="submitToggle($event, 'product-{{ $product->id }}')" class="inline-block">
                                @csrf
                                @method('PATCH')
                                <button type="submit" :disabled="isBusy()" aria-label="Ngừng bán {{ $product->name }}" class="px-2.5 py-1.5 text-xs font-semibold text-muted hover:text-error hover:bg-error-bg rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                                    Ngừng bán
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.products.activate', $product) }}" @submit.prevent="submitToggle($event, 'product-{{ $product->id }}')" class="inline-block">
                                @csrf
                                @method('PATCH')
                                <button type="submit" :disabled="isBusy()" aria-label="Mở bán {{ $product->name }}" class="px-2.5 py-1.5 text-xs font-semibold text-success hover:bg-success-bg rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                                    Mở bán
                                </button>
                            </form>
                        @endif

                        <div class="flex items-center gap-1.5">
                            <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-muted hover:text-main hover:bg-surface font-semibold text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                               href="{{ route('admin.products.duplicate', $product) }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                </svg>
                                <span>Nhân bản</span>
                            </a>
                            <a class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                               href="{{ route('admin.products.edit', $product) }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                                <span>Sửa</span>
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="p-10 text-center text-xs text-muted">Chưa có sản phẩm.</div>
            @endforelse
        </div>

        {{-- Desktop table layout (>= md) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs text-muted border-collapse">
                <thead class="bg-surface text-main font-bold border-b border-border">
                    <tr>
                        <th class="px-3 py-3.5 w-16 whitespace-nowrap">Ảnh</th>
                        <th class="px-3 py-3.5 min-w-[12rem]">Sản phẩm</th>
                        <th class="px-3 py-3.5 whitespace-nowrap">Danh mục</th>
                        <th class="px-3 py-3.5 whitespace-nowrap">Giá bán</th>
                        <th class="px-3 py-3.5 text-center whitespace-nowrap">Tồn kho</th>
                        <th class="px-3 py-3.5 text-center whitespace-nowrap">Trạng thái</th>
                        <th class="px-3 py-3.5 text-center whitespace-nowrap">Biến thể</th>
                        <th class="px-3 py-3.5 whitespace-nowrap hidden 2xl:table-cell">Ngày tạo</th>
                        <th class="px-3 py-3.5 text-right whitespace-nowrap">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border font-medium">
                @forelse ($products as $product)
                    @php($thumbnail = $product->colors->first()?->thumbnail)
                    @php($stock = (int) ($product->total_stock ?? 0))
                    <tr class="hover:bg-surface/50 transition-colors">
                        <td class="px-3 py-3.5 whitespace-nowrap">
                            <a href="{{ route('admin.products.show', $product) }}" class="block w-12 h-12 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                                @if ($thumbnail)
                                    <img src="{{ $thumbnail->thumb_64_url ?? $thumbnail->thumbnail_url ?? $images->imageUrl($thumbnail) }}" alt="{{ $product->name }}" class="w-12 h-12 object-cover rounded-xl border border-border bg-surface" loading="lazy" decoding="async" width="48" height="48">
                                @else
                                    <div class="flex w-12 h-12 items-center justify-center rounded-xl border border-border bg-surface text-[9px] text-muted text-center px-0.5 leading-tight">Chưa có ảnh</div>
                                @endif
                            </a>
                        </td>
                        <td class="px-3 py-3.5 min-w-[12rem] max-w-[17rem]">
                            <a href="{{ route('admin.products.show', $product) }}" class="font-bold text-main hover:text-accent hover:underline text-sm block leading-snug break-words transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                {{ $product->name }}
                            </a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="text-[11px] text-muted font-mono truncate max-w-[11rem]">{{ $product->slug }}</span>
                                <a href="{{ route('admin.products.show', $product) }}" class="text-[11px] font-semibold text-muted hover:text-accent underline decoration-border hover:decoration-accent underline-offset-2 transition-colors whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                    Chi tiết
                                </a>
                            </div>
                        </td>
                        <td class="px-3 py-3.5 text-main font-bold whitespace-nowrap">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-3 py-3.5 whitespace-nowrap font-mono tabular-nums">
                            @if ($product->promotion_price !== null && $product->promotion_price < $product->base_price)
                                <span class="font-extrabold text-accent text-sm block">{{ number_format($product->promotion_price, 0, ',', '.') }} ₫</span>
                                <span class="text-[11px] text-muted line-through">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @else
                                <span class="font-extrabold text-main text-sm block">{{ number_format($product->base_price, 0, ',', '.') }} ₫</span>
                            @endif
                        </td>
                        <td class="px-3 py-3.5 text-center whitespace-nowrap">
                            <span @class([
                                'inline-flex items-center justify-center whitespace-nowrap border px-2.5 py-1 rounded-lg font-mono tabular-nums font-bold text-[11px]',
                                'bg-surface text-main border-border' => $stock > 0,
                                'bg-error-bg text-error border-error/30' => $stock <= 0,
                            ])>{{ $stock }} đôi</span>
                        </td>
                        <td class="px-3 py-3.5 text-center whitespace-nowrap">
                            <div class="inline-flex flex-col items-center gap-1">
                                @if ($product->is_active)
                                    <span class="inline-flex items-center whitespace-nowrap bg-success-bg text-success border border-success/30 px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đang bán</span>
                                    <form method="POST" action="{{ route('admin.products.deactivate', $product) }}" @submit.prevent="submitToggle($event, 'product-{{ $product->id }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" :disabled="isBusy()" aria-label="Ngừng bán {{ $product->name }}" class="text-[11px] font-semibold text-muted hover:text-error underline decoration-border hover:decoration-error underline-offset-2 transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                            Ngừng bán
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center whitespace-nowrap bg-muted/15 text-muted border border-border px-2.5 py-0.5 rounded-full font-bold text-[11px]">Đã ẩn</span>
                                    <form method="POST" action="{{ route('admin.products.activate', $product) }}" @submit.prevent="submitToggle($event, 'product-{{ $product->id }}')" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" :disabled="isBusy()" aria-label="Mở bán {{ $product->name }}" class="text-[11px] font-semibold text-success hover:text-success/80 underline decoration-success/40 hover:decoration-success underline-offset-2 transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent rounded">
                                            Mở bán
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-3.5 text-center whitespace-nowrap">
                            <span class="inline-flex items-center whitespace-nowrap bg-surface text-muted border border-border px-2.5 py-0.5 rounded text-[11px] font-mono tabular-nums">{{ $product->variants_count }}</span>
                        </td>
                        <td class="px-3 py-3.5 whitespace-nowrap text-xs text-muted hidden 2xl:table-cell">{{ $product->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y') }}</td>
                        <td class="px-3 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                <a class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-accent/10 text-accent hover:bg-accent hover:text-accent-text font-bold text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                                   href="{{ route('admin.products.edit', $product) }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                    </svg>
                                    <span>Sửa</span>
                                </a>
                                <a class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-muted hover:text-main hover:bg-surface font-semibold text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                                   href="{{ route('admin.products.duplicate', $product) }}"
                                   title="Nhân bản sản phẩm {{ $product->name }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                    </svg>
                                    <span>Nhân bản</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-12 text-center text-xs text-muted">Chưa có sản phẩm.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-ui.pagination :paginator="$products" />
        </div>
    </div>
@endsection
