@extends('layouts.auth')

@section('title', 'Quên mật khẩu')
@section('heading', 'Quên mật khẩu?')
@section('subheading', 'Nhập email đã đăng ký, chúng tôi sẽ gửi mã xác nhận 6 chữ số.')
@section('switch')
    <a href="{{ route('login') }}" class="underline">Nhớ ra rồi? Đăng nhập tại đây!</a>
@endsection

@section('content')
    @error('password')<p role="alert" class="mb-4 rounded-md bg-red-500/30 px-4 py-3 text-sm">{{ $message }}</p>@enderror
    <x-auth.request-reset-form :action="route('password.email')" />
@endsection
