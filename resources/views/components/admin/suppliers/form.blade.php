@props(['action' => null, 'supplier' => [], 'method' => 'POST'])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-4">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    <x-forms.text-field name="code" label="Mã nhà cung cấp" :value="$supplier['code'] ?? ''" required />
    <x-forms.text-field name="name" label="Tên nhà cung cấp" :value="$supplier['name'] ?? ''" required />
    <x-forms.text-field name="contact_name" label="Người liên hệ" :value="$supplier['contact_name'] ?? ''" />
    <x-forms.text-field name="phone" label="Số điện thoại" :value="$supplier['phone'] ?? ''" type="tel" />
    <x-forms.text-field name="email" label="Email" :value="$supplier['email'] ?? ''" type="email" />
    <x-forms.text-field name="address" label="Địa chỉ" :value="$supplier['address'] ?? ''" />
    <x-ui.button type="submit" :disabled="! $action">Lưu nhà cung cấp</x-ui.button>
    @unless ($action)<p role="status">Chưa có quyền lưu.</p>@endunless
</form>
