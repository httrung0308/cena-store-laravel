@props(['provinces' => [], 'provinceCode' => null, 'wardCode' => null, 'disabled' => false, 'wardsUrl' => null])
{{-- wardsUrl: ward lookup URL containing the placeholder __CODE__ (e.g. route('locations.wards', ['provinceCode' => '__CODE__'])).
     Without it the selector stays disabled instead of guessing an endpoint. --}}
@php($selectedProvince = old('province_code', $provinceCode))
@php($selectedWard = old('ward_code', $wardCode))
<div class="space-y-4" x-data="addressSelector({
        provinces: @js($provinces),
        initialProvinceCode: @js(is_string($selectedProvince) ? $selectedProvince : null),
        initialWardCode: @js(is_string($selectedWard) ? $selectedWard : null),
        getWards: @if ($wardsUrl) (code, { signal }) => fetch(@js($wardsUrl).replace('__CODE__', encodeURIComponent(code)), { signal, headers: { Accept: 'application/json' } })
            .then((response) => { if (!response.ok) throw new Error('HTTP ' + response.status); return response.json(); })
            .then((body) => body.data) @else null @endif,
    })" x-init="initialize()">
    <div><label for="address-province" class="text-lg font-semibold">Tỉnh / thành phố</label>
        <select id="address-province" name="province_code" class="input block w-full" x-on:change="changeProvince($event.target.value)" @disabled($disabled) x-bind:disabled="!available">
            <option value="">Chọn tỉnh / thành phố</option>
            <template x-for="province in provinces" :key="province.code"><option x-bind:value="province.code" x-text="province.name" x-bind:selected="provinceCode === province.code"></option></template>
        </select><x-forms.field-error name="province_code" />
    </div>
    <div><label for="address-ward" class="text-lg font-semibold">Phường / xã / đặc khu</label>
        <select id="address-ward" name="ward_code" class="input block w-full" x-on:change="selectWard($event.target.value)" @disabled($disabled) x-bind:disabled="!available || loading || wards.length === 0">
            <option value="">Chọn phường / xã / đặc khu</option>
            <template x-for="ward in wards" :key="ward.code"><option x-bind:value="ward.code" x-text="ward.name" x-bind:selected="wardCode === ward.code"></option></template>
        </select><x-forms.field-error name="ward_code" />
        <p x-show="loading" role="status" class="text-sm">Đang tải xã/phường...</p>
        <p x-show="error" role="alert" class="text-sm text-error"><span x-text="error"></span> <button type="button" class="underline" x-on:click="retry()">Thử lại</button></p>
    </div>
    <p x-show="!available" role="status" class="text-xs text-muted">Chưa có nguồn dữ liệu địa giới được kết nối.</p>
</div>
