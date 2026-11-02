@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp')
@section('eyebrow', 'Quản lý nhà cung cấp')
@section('actions')
    <a class="btn-pill" href="{{ route('admin.suppliers.index') }}">Quay lại danh sách</a>
@endsection
@section('content')
    <div class="max-w-3xl">
        <x-admin.suppliers.form :action="$record->exists ? route('admin.suppliers.update', $record) : route('admin.suppliers.store')" :method="$record->exists ? 'PUT' : 'POST'" :supplier="$record->toArray()" />
    </div>
@endsection
