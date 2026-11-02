@extends('layouts.auth')

@section('title', 'Tạo tài khoản')

@section('content')
    <x-auth.register-form :action="route('register.store')" :login-url="route('login')" />
@endsection
