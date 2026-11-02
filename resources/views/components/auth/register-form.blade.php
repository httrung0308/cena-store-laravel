@props(['action' => null, 'loginUrl' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="flex flex-col gap-5" aria-label="Đăng ký">
    @csrf
    <x-forms.text-field name="full_name" id="register-name" label="Họ và tên" autocomplete="name" required />
    <x-forms.text-field name="email" id="register-email" label="Email" type="email" autocomplete="email" required />
    <x-forms.text-field name="phone" id="register-phone" label="Số điện thoại (không bắt buộc)" type="tel" autocomplete="tel" />
    <div><x-forms.password-field name="password" id="register-password" label="Mật khẩu" autocomplete="new-password" /><x-forms.field-error name="password" /></div>
    <div><x-forms.password-field name="password_confirmation" id="register-confirm" label="Nhập lại mật khẩu" autocomplete="new-password" /><x-forms.field-error name="password_confirmation" /></div>
    <x-ui.button type="submit" :disabled="! $action" class="w-full">Đăng ký</x-ui.button>
    @unless ($action)<p role="status" class="text-sm text-zinc-500">Đăng ký chưa khả dụng.</p>@endunless
    @if ($loginUrl)<a href="{{ $loginUrl }}" class="text-sm underline">Đã có tài khoản?</a>@endif
</form>
