@extends('layouts.admin')
@section('title', 'Quản Lý Danh Mục')
@section('eyebrow', 'QUẢN TRỊ DANH MỤC')
@section('actions')
    <a href="{{ route('admin.categories.create') }}" class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5">+ Thêm danh mục</a>
@endsection
@section('content')
    <x-admin.categories.table :categories="$paginator->map(fn ($item) => array_merge($item->toArray(), ['editUrl' => route('admin.categories.edit', $item), 'deactivateUrl' => $item->is_active ? route('admin.categories.deactivate', $item) : null]))->all()" :search="$search" :search-url="route('admin.categories.index')" :paginator="$paginator" />
@endsection
