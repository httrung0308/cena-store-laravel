@props(['action' => null, 'reauthMessage' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-5" aria-label="Đổi mật khẩu">
    @csrf
    @method('PATCH')
    <div><x-forms.password-field name="current_password" label="Mật khẩu hiện tại" autocomplete="current-password" /><x-forms.field-error name="current_password" /></div>
    <div><x-forms.password-field name="password" label="Mật khẩu mới" autocomplete="new-password" /><x-forms.field-error name="password" /></div>
    <div><x-forms.password-field name="password_confirmation" label="Nhập lại mật khẩu" autocomplete="new-password" /><x-forms.field-error name="password_confirmation" /></div>
    @if ($reauthMessage)<p role="status" class="text-sm">{{ $reauthMessage }}</p>@endif
    <x-ui.button type="submit" :disabled="! $action">Đổi mật khẩu</x-ui.button>
    @unless ($action)<p role="status">Chức năng chưa khả dụng.</p>@endunless
</form>
