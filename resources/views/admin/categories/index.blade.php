@extends('layouts.admin')
@section('title','Danh mục')
@section('content')
<a href="{{ route('admin.categories.create') }}" class="btn mb-4 inline-block">Thêm mới</a>
<x-admin.categories.table :categories="$paginator->map(fn($item)=>array_merge($item->toArray(),['editUrl'=>route('admin.categories.edit',$item),'deactivateUrl'=>$item->is_active ? route('admin.categories.deactivate',$item) : null]))->all()" :search="$search" :search-url="route('admin.categories.index')" :paginator="$paginator" />
@endsection
