@extends('layouts.auth')

@section('title', 'Tạo tài khoản')
@section('heading', 'Gia nhập cùng Nike.')
@section('subheading', 'Tạo tài khoản để mua sắm, theo dõi đơn hàng và nhận ưu đãi.')
@section('switch')
    <a href="{{ route('login') }}" class="underline">Đã có tài khoản? Đăng nhập tại đây!</a>
@endsection

@section('content')
    <x-auth.register-form :action="route('register.store')" />
@endsection
