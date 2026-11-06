@extends('layouts.store')

@section('title', $address ? 'Sửa địa chỉ' : 'Thêm địa chỉ')

@section('content')
    <section class="mx-auto max-w-2xl space-y-8 py-6 sm:py-10">
        <div class="space-y-3">
            <a href="{{ route('account.addresses.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-accent">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                <span>Quản lý địa chỉ</span>
            </a>
            <h1 class="text-2xl sm:text-4xl font-extrabold text-main tracking-tight">{{ $address ? 'Sửa địa chỉ' : 'Thêm địa chỉ' }}</h1>
        </div>
        <form method="POST" action="{{ $address ? route('account.addresses.update', $address->id) : route('account.addresses.store') }}" class="flex flex-col gap-6 rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-10" novalidate>
            @csrf
            @if ($address)
                @method('PUT')
            @elseif (request()->query('return_to') === 'checkout')
                <input type="hidden" name="return_to" value="checkout">
            @endif
            <x-forms.text-field name="recipient_name" id="address-recipient" label="Tên người nhận" autocomplete="name" :value="$address?->recipient_name" required />
            <x-forms.text-field name="phone" id="address-phone" label="Số điện thoại" type="tel" autocomplete="tel" :value="$address?->phone" required />
            <x-account.address-fields
                :provinces="$provinces"
                :province-code="$address?->province_code_snapshot"
                :ward-code="$address?->ward_code_snapshot"
                :wards-url="route('locations.wards', ['provinceCode' => '__CODE__'])" />
            <x-forms.text-field name="street_address" id="address-street" label="Số nhà, tên đường" autocomplete="street-address" :value="$address?->street_address" required />
            @unless ($address?->is_default)
                <x-forms.choice-group name="is_default" label="Tùy chọn" :options="[['value' => '1', 'label' => 'Đặt làm địa chỉ mặc định']]" />
            @endunless
            <div class="flex gap-4 pt-4 border-t border-border/60">
                <a href="{{ route('account.addresses.index') }}" class="btn-secondary flex-1 py-3 text-center text-sm font-semibold">Hủy</a>
                <button type="submit" class="btn flex-1 py-3 text-center text-sm font-semibold shadow-card">{{ $address ? 'Lưu thay đổi' : 'Thêm địa chỉ' }}</button>
            </div>
        </form>
    </section>
@endsection
