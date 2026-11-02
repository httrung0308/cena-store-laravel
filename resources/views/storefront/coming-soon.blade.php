@extends('layouts.store')

@section('title', 'Sản phẩm')

@section('content')
    <section class="mx-auto max-w-xl py-24 text-center">
        <h1 class="text-4xl font-extrabold">Sắp ra mắt</h1>
        <p class="mt-4 text-zinc-600">Danh sách sản phẩm đang được cập nhật. Vui lòng quay lại sau.</p>
        <a href="{{ route('home') }}" class="btn mt-8 inline-block">Về trang chủ</a>
    </section>
@endsection
