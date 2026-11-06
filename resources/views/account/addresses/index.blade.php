@extends('layouts.store')

@section('title', 'Sổ địa chỉ')

@section('content')
    <section class="py-6 sm:py-10">
        <div class="mx-auto flex max-w-4xl flex-col gap-8">
            <div class="space-y-3">
                <a href="{{ route('account.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-accent">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Tài khoản của tôi</span>
                </a>
                <h1 class="text-3xl font-bold font-serif text-main sm:text-5xl tracking-tight">Quản lý địa chỉ</h1>
            </div>
            @if (session('status'))
                <p role="status" class="rounded-xl border border-success/30 bg-success-bg p-4 text-sm font-medium text-success-text shadow-subtle">{{ session('status') }}</p>
            @endif
            <x-account.address-list :addresses="$addresses" />
            <a href="{{ route('account.addresses.create') }}" class="btn w-full py-3.5 text-center text-base font-semibold shadow-card">+ Thêm địa chỉ mới</a>
        </div>
    </section>
@endsection
