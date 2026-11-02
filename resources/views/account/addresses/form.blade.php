@extends('layouts.store')

@section('title', $address ? 'Sửa địa chỉ' : 'Thêm địa chỉ')

@section('content')
    <section class="mx-auto max-w-2xl space-y-8 py-10">
        <div class="space-y-4">
            <a href="{{ route('account.addresses.index') }}" class="inline-flex items-center gap-2 font-semibold text-zinc-600 transition hover:text-zinc-900">← Quản lý địa chỉ</a>
            <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">Tài khoản</p>
            <h1 class="text-4xl font-bold sm:text-5xl">{{ $address ? 'Sửa địa chỉ' : 'Thêm địa chỉ' }}</h1>
        </div>
        <form method="POST" action="{{ $address ? route('account.addresses.update', $address->id) : route('account.addresses.store') }}" class="flex flex-col gap-5 rounded-3xl border border-zinc-200 p-6 shadow-sm sm:p-10" novalidate>
            @csrf
            @if ($address)
                @method('PUT')
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
            <div class="flex gap-4">
                <a href="{{ route('account.addresses.index') }}" class="flex-1 rounded-full border border-zinc-300 py-4 text-center text-lg font-semibold transition hover:bg-zinc-100">Hủy</a>
                <button type="submit" class="btn flex-1 rounded-full py-4 text-lg font-semibold">{{ $address ? 'Lưu thay đổi' : 'Thêm địa chỉ' }}</button>
            </div>
        </form>
    </section>
@endsection
