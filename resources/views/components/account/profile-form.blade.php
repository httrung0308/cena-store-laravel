@props(['action' => null, 'profile' => [], 'avatarUrl' => null])
@php
    $profile = is_array($profile) ? $profile : [];
@endphp
<form @if ($action) action="{{ $action }}" @endif method="POST" enctype="multipart/form-data" class="space-y-5" aria-label="Hồ sơ cá nhân">
    @csrf
    @method('PATCH')
    @if ($avatarUrl)<img class="h-20 w-20 rounded-full object-cover" src="{{ $avatarUrl }}" alt="Ảnh đại diện hiện tại">@else<p class="text-sm text-zinc-500">Chưa có ảnh đại diện.</p>@endif
    <x-forms.text-field name="full_name" label="Họ và tên" :value="$profile['full_name'] ?? ''" autocomplete="name" required />
    <x-forms.text-field name="phone" label="Số điện thoại" :value="$profile['phone'] ?? ''" type="tel" autocomplete="tel" />
    <div><label for="profile-email" class="text-lg font-semibold">Email (chỉ đọc)</label><input id="profile-email" type="email" readonly value="{{ $profile['email'] ?? '' }}" class="input w-full bg-zinc-100" autocomplete="off"></div>
    <div><label for="profile-avatar" class="text-lg font-semibold">Ảnh đại diện</label><input id="profile-avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm"><x-forms.field-error name="avatar" /></div>
    <x-ui.button type="submit" :disabled="! $action">Lưu thay đổi</x-ui.button>
    @unless ($action)<p role="status">Chưa thể cập nhật hồ sơ.</p>@endunless
</form>
