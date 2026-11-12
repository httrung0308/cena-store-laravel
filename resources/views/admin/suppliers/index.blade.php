@extends('layouts.admin')
@section('title', 'Nhà Cung Cấp')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a href="{{ route('admin.suppliers.create') }}" class="px-5 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md flex items-center gap-1.5">+ Thêm nhà cung cấp</a>
@endsection
@section('content')
    <x-admin.suppliers.table :suppliers="$paginator->map(fn ($item) => array_merge($item->toArray(), [
        'editUrl' => route('admin.suppliers.edit', $item),
        'activateUrl' => ! $item->is_active ? route('admin.suppliers.activate', $item) : null,
        'deactivateUrl' => $item->is_active ? route('admin.suppliers.deactivate', $item) : null,
    ]))->all()" :search="$search" :search-url="route('admin.suppliers.index')" :paginator="$paginator" />
@endsection
