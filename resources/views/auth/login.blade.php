@extends('layouts.auth')

@section('title', 'Đăng nhập')

@section('content')
    <x-auth.login-form :action="route('login.store')" :register-url="route('register')" />
@endsection
