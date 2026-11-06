@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa Danh Mục' : 'Thêm Danh Mục')
@section('eyebrow', 'QUẢN TRỊ DANH MỤC')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.categories.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
    <div class="max-w-3xl">
        <x-admin.categories.form :action="$record->exists ? route('admin.categories.update', $record) : route('admin.categories.store')" :method="$record->exists ? 'PUT' : 'POST'" :category="$record->toArray()" :parents="$parents" :gender-options="$genderOptions" />
    </div>
@endsection
