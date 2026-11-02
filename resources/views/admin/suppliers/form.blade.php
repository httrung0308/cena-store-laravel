@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa bản ghi' : 'Thêm mới')
@section('content')
<div class="mx-auto max-w-3xl"><a class="underline" href="{{ route('admin.suppliers.index') }}">Quay lại danh sách</a>
<x-admin.suppliers.form :action="$record->exists ? route('admin.suppliers.update', $record) : route('admin.suppliers.store')" :method="$record->exists ? 'PUT' : 'POST'" :supplier="$record->toArray()"  />
</div>
@endsection
