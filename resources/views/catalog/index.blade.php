@extends('layouts.store')
@section('title', 'Giày — Shoes')
@section('content')
<h1 class="mx-auto mt-8 w-full max-w-7xl px-4 text-3xl font-semibold">Shoes</h1>
<x-catalog.listing :paginator="$paginator" :filter-props="$filterProps" />
@endsection
