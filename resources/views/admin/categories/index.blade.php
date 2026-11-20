@extends('layouts.admin')
@section('title', 'Quản Lý Đối Tượng')
@section('eyebrow', 'QUẢN TRỊ ĐỐI TƯỢNG')
@section('actions')
    <a href="{{ route('admin.categories.create') }}" class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5">+ Thêm đối tượng</a>
@endsection
@section('content')
    <x-admin.categories.table :categories="$paginator->map(fn ($item) => array_merge($item->toArray(), [
        'editUrl' => route('admin.categories.edit', $item),
        'activateUrl' => ! $item->is_active ? route('admin.categories.activate', $item) : null,
        'deactivateUrl' => $item->is_active ? route('admin.categories.deactivate', $item) : null,
    ]))->all()" :search="$search" :gender-group="$genderGroup" :active="$active" :search-url="route('admin.categories.index')" :paginator="$paginator" />
@endsection
