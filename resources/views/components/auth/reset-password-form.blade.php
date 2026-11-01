@props(['action' => null, 'expiresAt' => null, 'expired' => false])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-5" aria-label="Đặt mật khẩu mới">
    @csrf
    <div><x-forms.password-field name="password" label="Mật khẩu mới" autocomplete="new-password" /><x-forms.field-error name="password" /></div>
    <div><x-forms.password-field name="password_confirmation" label="Xác nhận mật khẩu" autocomplete="new-password" /><x-forms.field-error name="password_confirmation" /></div>
    @if ($expiresAt)<p class="text-xs">Hạn đặt lại: {{ $expiresAt }}</p>@endif
    @if ($expired)<p role="alert">Yêu cầu đã hết hạn.</p>@endif
    <x-ui.button type="submit" :disabled="! $action || $expired">Đặt lại mật khẩu</x-ui.button>
    @unless ($action)<p role="status">Chức năng chưa khả dụng.</p>@endunless
</form>
