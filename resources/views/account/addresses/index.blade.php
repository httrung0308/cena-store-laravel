@extends('layouts.store')

@section('title', 'Sổ địa chỉ')

@section('content')
    <section class="py-10 sm:px-8">
        <div class="mx-auto flex max-w-4xl flex-col gap-8">
            <div class="space-y-4">
                <a href="{{ route('account.index') }}" class="inline-flex items-center gap-2 font-semibold text-zinc-600 transition hover:text-zinc-900">← Tài khoản của tôi</a>
                <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">Tài khoản</p>
                <h1 class="text-4xl font-bold sm:text-5xl">Quản lý địa chỉ</h1>
            </div>
            @if (session('status'))
                <p role="status" class="rounded-3xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</p>
            @endif
            <x-account.address-list :addresses="$addresses" />
            <a href="{{ route('account.addresses.create') }}" class="btn w-full py-4 text-center text-lg font-semibold">+ Thêm địa chỉ mới</a>
        </div>
    </section>
@endsection
