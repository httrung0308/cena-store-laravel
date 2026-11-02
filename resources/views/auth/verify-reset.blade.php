@extends('layouts.auth')

@section('title', 'Nhập mã xác nhận')
@section('heading', 'Kiểm tra hộp thư.')
@section('subheading', 'Nhập mã 6 chữ số vừa được gửi tới email của bạn.')
@section('switch')
    <a href="{{ route('password.request') }}" class="underline">Dùng email khác</a>
@endsection

@section('content')
    <x-auth.verify-reset-form :action="route('password.verify')" :attempts-remaining="$attemptsLeft" />
    <form method="POST" action="{{ route('password.resend') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm underline">Chưa nhận được mã? Gửi lại</button>
    </form>
@endsection
