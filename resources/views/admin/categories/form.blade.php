@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa bản ghi' : 'Thêm mới')
@section('content')
<div class="mx-auto max-w-3xl"><a class="underline" href="{{ route('admin.categories.index') }}">Quay lại danh sách</a>
<x-admin.categories.form :action="$record->exists ? route('admin.categories.update', $record) : route('admin.categories.store')" :method="$record->exists ? 'PUT' : 'POST'" :category="$record->toArray()" :parents="$parents" :gender-options="$genderOptions" />
</div>
@endsection
