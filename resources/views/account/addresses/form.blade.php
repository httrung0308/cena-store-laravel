@extends('layouts.store')

@section('title', $address ? 'Sửa địa chỉ' : 'Thêm địa chỉ')

@section('content')
    <section class="mx-auto max-w-xl space-y-6">
        <div>
            <a href="{{ route('account.addresses.index') }}" class="text-sm underline">← Sổ địa chỉ</a>
            <h1 class="mt-2 text-3xl font-extrabold">{{ $address ? 'Sửa địa chỉ' : 'Thêm địa chỉ' }}</h1>
        </div>
        <form method="POST" action="{{ $address ? route('account.addresses.update', $address->id) : route('account.addresses.store') }}" class="flex flex-col gap-5" novalidate>
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
            <button type="submit" class="btn w-full">{{ $address ? 'Lưu thay đổi' : 'Thêm địa chỉ' }}</button>
        </form>
    </section>
@endsection
