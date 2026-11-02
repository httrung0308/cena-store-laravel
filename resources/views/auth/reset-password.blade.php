@extends('layouts.auth')

@section('title', 'Đặt mật khẩu mới')
@section('heading', 'Đặt mật khẩu mới.')
@section('subheading', 'Sau khi đổi, bạn cần đăng nhập lại trên mọi thiết bị.')

@section('content')
    <x-auth.reset-password-form :action="route('password.update')" />
@endsection
