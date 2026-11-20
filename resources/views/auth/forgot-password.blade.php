@extends('layouts.auth')

@section('title', 'Quên mật khẩu')
@section('heading', 'Quên mật khẩu?')
@section('subheading', 'Nhập email đã đăng ký, chúng tôi sẽ gửi mã xác nhận 6 chữ số.')
@section('switch')
    <a href="{{ route('login') }}" class="font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Nhớ ra rồi? Đăng nhập tại đây!</a>
@endsection

@section('content')
    @error('password')
        <div role="alert" class="mb-5 rounded-xl border border-error/30 bg-error-bg px-4 py-3 text-sm font-medium text-error-text shadow-subtle">{{ $message }}</div>
    @enderror
    <x-auth.request-reset-form :action="route('password.email')" />
@endsection
