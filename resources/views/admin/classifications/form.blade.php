@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa Nhãn Phân Loại' : 'Thêm Nhãn Phân Loại')
@section('eyebrow', 'QUẢN TRỊ DANH MỤC & PHÂN LOẠI')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.classifications.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
    <div class="max-w-2xl mx-auto">
        <x-admin.classifications.form
            :action="$record->exists ? route('admin.classifications.update', $record) : route('admin.classifications.store')"
            :method="$record->exists ? 'PUT' : 'POST'"
            :record="$record"
            :groups="$groups"
            :products-count="$productsCount"
            :home-nav-counts="$homeNavCounts"
            :home-nav-limit="$homeNavLimit"
        />
    </div>
@endsection
