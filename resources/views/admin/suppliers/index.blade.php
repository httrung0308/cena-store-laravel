@extends('layouts.admin')
@section('title','Nhà cung cấp')
@section('content')
<a href="{{ route('admin.suppliers.create') }}" class="btn mb-4 inline-block">Thêm mới</a>
<x-admin.suppliers.table :suppliers="$paginator->map(fn($item)=>array_merge($item->toArray(),['editUrl'=>route('admin.suppliers.edit',$item),'deactivateUrl'=>$item->is_active ? route('admin.suppliers.deactivate',$item) : null]))->all()" :search="$search" :search-url="route('admin.suppliers.index')" :paginator="$paginator" />
@endsection
