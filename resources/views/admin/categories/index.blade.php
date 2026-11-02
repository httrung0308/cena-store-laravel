@extends('layouts.admin')
@section('title', 'Danh mục')
@section('eyebrow', 'Quản lý danh mục')
@section('actions')
    <a href="{{ route('admin.categories.create') }}" class="btn-pill-dark">+ Thêm danh mục</a>
@endsection
@section('content')
    <x-admin.categories.table :categories="$paginator->map(fn ($item) => array_merge($item->toArray(), ['editUrl' => route('admin.categories.edit', $item), 'deactivateUrl' => $item->is_active ? route('admin.categories.deactivate', $item) : null]))->all()" :search="$search" :search-url="route('admin.categories.index')" :paginator="$paginator" />
@endsection
