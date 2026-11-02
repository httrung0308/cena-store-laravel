@extends('layouts.store')

@section('title', 'Sổ địa chỉ')

@section('content')
    <section class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-extrabold">Sổ địa chỉ</h1>
            <a href="{{ route('account.addresses.create') }}" class="btn">Thêm địa chỉ</a>
        </div>
        @if (session('status'))
            <p role="status" class="border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>
        @endif
        <x-account.address-list :addresses="$addresses" />
    </section>
@endsection
