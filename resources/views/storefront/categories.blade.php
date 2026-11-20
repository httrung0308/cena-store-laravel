@extends('layouts.store')

@section('title', 'Danh mục & Phân loại')
@section('meta_description', 'Khám phá đầy đủ các nhóm Đối tượng, Kiểu dáng và Mục đích sử dụng giày tại Cena Store để lọc nhanh mẫu giày phù hợp.')

@php
    $categoryPool = collect($categories)
        ->map(fn (array $category): string => trim(($category['name'] ?? '').' '.($category['tagline'] ?? '')))
        ->values()
        ->all();

    $silhouettePool = $silhouettes
        ->map(fn ($item): string => trim($item->name.' '.($item->description ?? '')))
        ->values()
        ->all();

    $activityPool = $activities
        ->map(fn ($item): string => trim($item->name.' '.($item->description ?? '')))
        ->values()
        ->all();
@endphp

@section('content')
<div class="mx-auto max-w-7xl space-y-10 py-2 sm:py-4"
     x-data="{
        query: '',
        categoryPool: {{ Js::from($categoryPool) }},
        silhouettePool: {{ Js::from($silhouettePool) }},
        activityPool: {{ Js::from($activityPool) }},
        normalize(value) {
            return String(value || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/đ/g, 'd')
                .trim();
        },
        matches(text) {
            const q = this.normalize(this.query);
            if (!q) return true;
            return this.normalize(text).includes(q);
        },
        get hasQuery() {
            return this.normalize(this.query).length > 0;
        },
        get hasCategoryMatches() {
            return this.categoryPool.some((text) => this.matches(text));
        },
        get hasSilhouetteMatches() {
            return this.silhouettePool.some((text) => this.matches(text));
        },
        get hasActivityMatches() {
            return this.activityPool.some((text) => this.matches(text));
        },
        get hasAnyMatch() {
            return this.hasCategoryMatches || this.hasSilhouetteMatches || this.hasActivityMatches;
        }
     }">

    {{-- Breadcrumb --}}
    <nav aria-label="Đường dẫn" class="flex items-center gap-2 text-xs text-muted">
        <a href="{{ route('home') }}" class="transition-colors hover:text-accent">Trang chủ</a>
        <span aria-hidden="true">/</span>
        <span class="font-medium text-main">Danh mục &amp; Phân loại</span>
    </nav>

    {{-- Page Header + Quick Search --}}
    <div class="flex flex-col gap-5 border-b border-border/80 pb-6 md:flex-row md:items-end md:justify-between">
        <div class="space-y-2 max-w-2xl">
            <h1 class="text-2xl font-extrabold tracking-tight text-main sm:text-4xl">
                Danh mục &amp; Phân loại
            </h1>
            <p class="text-sm leading-relaxed text-muted sm:text-base">
                Chọn đối tượng, kiểu dáng hoặc mục đích sử dụng để xem danh sách giày đã lọc sẵn theo nhu cầu của bạn.
            </p>
        </div>

        <div class="w-full md:w-80 shrink-0">
            <label for="category-quick-search" class="sr-only">Tìm nhanh danh mục hoặc phân loại</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input id="category-quick-search"
                       type="search"
                       x-model.debounce.120ms="query"
                       placeholder="Tìm nhanh kiểu dáng, mục đích..."
                       class="input w-full pl-10 pr-9 py-2.5 text-sm shadow-subtle">
                <button type="button"
                        x-cloak
                        x-show="query.length > 0"
                        @click="query = ''"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg p-1 text-muted transition-colors hover:bg-surface hover:text-main"
                        aria-label="Xóa từ khóa tìm nhanh">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Empty state khi tìm nhanh không có kết quả --}}
    <div x-cloak
         x-show="hasQuery && !hasAnyMatch"
         role="status"
         class="rounded-2xl border border-dashed border-border bg-surface-card p-10 text-center shadow-subtle space-y-3">
        <p class="text-base font-bold text-main">
            Không tìm thấy danh mục hoặc phân loại phù hợp
        </p>
        <p class="text-sm text-muted">
            Không có mục nào khớp với từ khóa <span class="font-semibold text-main" x-text="'“' + query.trim() + '”'"></span>. Hãy thử từ khóa ngắn hơn hoặc xóa tìm kiếm để xem toàn bộ.
        </p>
        <div class="pt-1">
            <button type="button"
                    @click="query = ''"
                    class="btn-secondary py-2 px-4 text-xs">
                Xóa tìm kiếm
            </button>
        </div>
    </div>

    {{-- 1. Nhóm Đối tượng --}}
    <section aria-labelledby="cat-heading-gender"
             x-show="!hasQuery || hasCategoryMatches"
             class="space-y-4">
        <div class="relative border-b border-border/80 pb-3">
            <h2 id="cat-heading-gender" class="text-xl font-extrabold tracking-tight text-main sm:text-2xl">
                Đối tượng
            </h2>
            <span aria-hidden="true" class="pointer-events-none absolute -bottom-px left-0 h-[3px] w-12 rounded-full bg-accent"></span>
        </div>

        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($categories as $category)
                @php($categoryText = trim(($category['name'] ?? '').' '.($category['tagline'] ?? '')))
                <a href="{{ $category['url'] }}"
                   x-show="matches({{ Js::from($categoryText) }})"
                   class="group flex items-start gap-3.5 rounded-2xl border border-border bg-surface-card p-4 text-main shadow-subtle transition-colors hover:border-accent hover:bg-surface">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border/70 bg-surface text-muted transition-colors group-hover:border-accent group-hover:text-accent">
                        @if ($category['slug'] === 'men')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3h5m0 0v5m0-5l-6 6M14 14a5 5 0 11-10 0 5 5 0 0110 0z"/></svg>
                        @elseif ($category['slug'] === 'women')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14a5 5 0 100-10 5 5 0 000 10zm0 0v6m-3-3h6"/></svg>
                        @elseif ($category['slug'] === 'kids')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex items-start justify-between gap-2">
                            <span class="break-words text-sm font-bold leading-snug text-main transition-colors group-hover:text-accent sm:text-base">
                                {{ $category['name'] }}
                            </span>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted/70 transition-transform group-hover:translate-x-0.5 group-hover:text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                        @if (! empty($category['tagline']))
                            <p class="text-xs leading-relaxed text-muted">
                                {{ $category['tagline'] }}
                            </p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- 2. Nhóm Kiểu dáng --}}
    <section aria-labelledby="cat-heading-silhouette"
             x-show="(!hasQuery && {{ $silhouettes->isEmpty() ? 'true' : 'false' }}) || hasSilhouetteMatches"
             class="space-y-4">
        <div class="relative border-b border-border/80 pb-3">
            <h2 id="cat-heading-silhouette" class="text-xl font-extrabold tracking-tight text-main sm:text-2xl">
                Kiểu dáng
            </h2>
            <span aria-hidden="true" class="pointer-events-none absolute -bottom-px left-0 h-[3px] w-12 rounded-full bg-accent"></span>
        </div>

        @if ($silhouettes->isEmpty())
            <div class="rounded-2xl border border-dashed border-border bg-surface/40 p-8 text-center text-sm text-muted">
                Hiện chưa có phân loại kiểu dáng nào đang hoạt động.
            </div>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($silhouettes as $silhouette)
                    @php($silhouetteText = trim($silhouette->name.' '.($silhouette->description ?? '')))
                    <a href="{{ route('shoes.index', ['silhouette' => [$silhouette->slug]]) }}"
                       x-show="matches({{ Js::from($silhouetteText) }})"
                       class="group flex flex-col justify-between gap-2 rounded-xl border border-border bg-surface-card p-3.5 text-main shadow-subtle transition-colors hover:border-accent hover:bg-surface">
                        <div class="flex items-start justify-between gap-2">
                            <span class="break-words text-sm font-bold leading-snug text-main transition-colors group-hover:text-accent">
                                {{ $silhouette->name }}
                            </span>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted/70 transition-transform group-hover:translate-x-0.5 group-hover:text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                        @if (! empty($silhouette->description))
                            <p class="text-xs leading-relaxed text-muted line-clamp-2">
                                {{ $silhouette->description }}
                            </p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- 3. Nhóm Mục đích sử dụng --}}
    <section aria-labelledby="cat-heading-activity"
             x-show="(!hasQuery && {{ $activities->isEmpty() ? 'true' : 'false' }}) || hasActivityMatches"
             class="space-y-4">
        <div class="relative border-b border-border/80 pb-3">
            <h2 id="cat-heading-activity" class="text-xl font-extrabold tracking-tight text-main sm:text-2xl">
                Mục đích sử dụng
            </h2>
            <span aria-hidden="true" class="pointer-events-none absolute -bottom-px left-0 h-[3px] w-12 rounded-full bg-accent-sub"></span>
        </div>

        @if ($activities->isEmpty())
            <div class="rounded-2xl border border-dashed border-border bg-surface/40 p-8 text-center text-sm text-muted">
                Hiện chưa có phân loại mục đích sử dụng nào đang hoạt động.
            </div>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($activities as $activity)
                    @php($activityText = trim($activity->name.' '.($activity->description ?? '')))
                    <a href="{{ route('shoes.index', ['activity' => [$activity->slug]]) }}"
                       x-show="matches({{ Js::from($activityText) }})"
                       class="group flex flex-col justify-between gap-2 rounded-xl border border-accent/25 bg-surface-card p-3.5 text-main shadow-subtle transition-colors hover:border-accent hover:bg-accent/5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2 min-w-0">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                                <span class="break-words text-sm font-bold leading-snug text-main transition-colors group-hover:text-accent">
                                    {{ $activity->name }}
                                </span>
                            </div>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted/70 transition-transform group-hover:translate-x-0.5 group-hover:text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                        @if (! empty($activity->description))
                            <p class="text-xs leading-relaxed text-muted line-clamp-2">
                                {{ $activity->description }}
                            </p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </section>

</div>
@endsection
