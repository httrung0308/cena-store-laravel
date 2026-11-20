@extends('layouts.auth')

@section('title', 'Tạo tài khoản')
@section('heading', 'Gia nhập cùng Cena.')
@section('subheading', 'Tạo tài khoản để mua sắm, theo dõi đơn hàng và nhận ưu đãi.')
@section('switch')
    <a href="{{ route('login') }}" class="font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Đã có tài khoản? Đăng nhập tại đây!</a>
@endsection

@section('content')
    <x-auth.register-form :action="route('register.store')" />
@endsection
