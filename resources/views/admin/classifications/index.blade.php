@extends('layouts.admin')
@section('title', 'Quản Lý Phân Loại')
@section('eyebrow', 'QUẢN TRỊ DANH MỤC & PHÂN LOẠI')
@section('actions')
    <a href="{{ route('admin.classifications.create') }}" class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5">+ Thêm nhãn mới</a>
@endsection
@section('content')
    <x-admin.classifications.table
        :classifications="$paginator->items()"
        :search="$search"
        :group="$group"
        :active="$active"
        :home-nav="$homeNav"
        :home-nav-counts="$homeNavCounts"
        :home-nav-limit="$homeNavLimit"
        :search-url="route('admin.classifications.index')"
        :paginator="$paginator"
    />
@endsection
