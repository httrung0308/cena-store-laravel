@extends('layouts.store')
@section('title', 'Giày — Shoes')
@section('content')
<div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
    <nav class="mb-4 flex items-center gap-2 text-xs text-muted/70">
        <a href="{{ route('home') }}" class="transition-colors hover:text-accent">Trang chủ</a>
        <span>/</span>
        <span class="font-medium text-main">Danh mục giày</span>
    </nav>
    <div class="mb-6 flex flex-col border-b border-border pb-5 sm:flex-row sm:items-baseline sm:justify-between">
        <div>
            <h1 class="text-3xl font-light tracking-tight text-main sm:text-4xl">Tất Cả Sản Phẩm <span class="ml-2 font-serif text-sm italic tracking-widest text-accent uppercase">Shoes</span></h1>
            <p class="mt-1 text-xs font-light text-muted sm:text-sm">Những mẫu giày thể thao chính hãng tuyển chọn cho cảm giác nâng niu tự nhiên.</p>
        </div>
    </div>
</div>
<x-catalog.listing :paginator="$paginator" :filter-props="$filterProps" />
@endsection
