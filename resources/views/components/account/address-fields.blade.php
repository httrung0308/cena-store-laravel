@props(['provinces' => [], 'provinceCode' => null, 'wardCode' => null, 'disabled' => false])
<div class="space-y-4" x-data="addressSelector({provinces: @js($provinces), initialProvinceCode: @js($provinceCode), initialWardCode: @js($wardCode)})" x-init="initialize()">
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
        <p x-show="loading" role="status" class="text-sm">Đang tải xã/phường...</p><p x-show="error" role="alert" x-text="error" class="text-sm text-red-500"></p>
    </div>
    <p x-show="!available" role="status" class="text-xs text-zinc-500">Chưa có nguồn dữ liệu địa giới được kết nối.</p>
</div>
