@extends('layouts.auth')

@section('title', 'Đăng nhập')
@section('heading', 'Chào mừng trở lại.')
@section('switch')
    <a href="{{ route('register') }}" class="underline">Chưa có tài khoản? Đăng ký tại đây!</a>
@endsection

@section('content')
    <x-auth.login-form :action="route('login.store')" />
@endsection
