@extends('layouts.auth')

@section('title', 'Nhập mã xác nhận')
@section('heading', 'Kiểm tra hộp thư.')
@section('subheading', 'Nhập mã 6 chữ số vừa được gửi tới email của bạn.')
@section('switch')
    <a href="{{ route('password.request') }}" class="font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Dùng email khác</a>
@endsection

@section('content')
    <x-auth.verify-reset-form :action="route('password.verify')" :attempts-remaining="$attemptsLeft" />
    <form method="POST" action="{{ route('password.resend') }}" class="mt-5 text-center">
        @csrf
        <button type="submit" class="text-xs font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">Chưa nhận được mã? Gửi lại</button>
    </form>
@endsection
