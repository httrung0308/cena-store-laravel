@props(['action' => null, 'supplier' => [], 'method' => 'POST'])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="admin-card space-y-5">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    <h2 class="text-xl font-semibold text-main">Thông tin nhà cung cấp</h2>
    <div class="grid gap-4 md:grid-cols-2">
        <x-admin.field name="code" label="Mã nhà cung cấp" :value="$supplier['code'] ?? ''" required />
        <x-admin.field name="name" label="Tên nhà cung cấp" :value="$supplier['name'] ?? ''" required />
        <x-admin.field name="phone" label="Số điện thoại" :value="$supplier['phone'] ?? ''" type="tel" required />
        <x-admin.field name="email" label="Email" :value="$supplier['email'] ?? ''" type="email" />
        <div class="md:col-span-2"><x-admin.field name="address" label="Địa chỉ" :value="$supplier['address'] ?? ''" /></div>
    </div>
    <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2 text-sm font-semibold text-main cursor-pointer"><input type="checkbox" name="is_active" value="1" class="native-checkbox accent-accent" @checked(old('is_active', $supplier['is_active'] ?? true))> Đang hợp tác</label>
    <button type="submit" class="btn-pill-dark" @disabled(! $action)>Lưu nhà cung cấp</button>
    @unless ($action)<p role="status" class="text-sm text-error">Chưa có quyền lưu.</p>@endunless
</form>
