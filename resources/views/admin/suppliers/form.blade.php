@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa Nhà Cung Cấp' : 'Thêm Nhà Cung Cấp')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.suppliers.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
    <div class="max-w-3xl mx-auto">
        <x-admin.suppliers.form :action="$record->exists ? route('admin.suppliers.update', $record) : route('admin.suppliers.store')" :method="$record->exists ? 'PUT' : 'POST'" :supplier="$record->toArray()" />
    </div>
@endsection
