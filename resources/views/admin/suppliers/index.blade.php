@extends('layouts.admin')
@section('title', 'Nhà cung cấp')
@section('eyebrow', 'Quản lý nhà cung cấp')
@section('actions')
    <a href="{{ route('admin.suppliers.create') }}" class="btn-pill-dark">+ Thêm nhà cung cấp</a>
@endsection
@section('content')
    <x-admin.suppliers.table :suppliers="$paginator->map(fn ($item) => array_merge($item->toArray(), ['editUrl' => route('admin.suppliers.edit', $item), 'deactivateUrl' => $item->is_active ? route('admin.suppliers.deactivate', $item) : null]))->all()" :search="$search" :search-url="route('admin.suppliers.index')" :paginator="$paginator" />
@endsection
