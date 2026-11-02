@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa danh mục' : 'Thêm danh mục')
@section('eyebrow', 'Quản lý danh mục')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.categories.index') }}">Quay lại danh sách</a>
@endsection
@section('content')
    <div class="max-w-3xl">
        <x-admin.categories.form :action="$record->exists ? route('admin.categories.update', $record) : route('admin.categories.store')" :method="$record->exists ? 'PUT' : 'POST'" :category="$record->toArray()" :parents="$parents" :gender-options="$genderOptions" />
    </div>
@endsection
