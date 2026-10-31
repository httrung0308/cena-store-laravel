@props(['action' => null, 'forgotUrl' => null, 'registerUrl' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="flex flex-col gap-5" aria-label="Đăng nhập">
    @csrf
    <x-forms.text-field name="email" id="login-email" label="Email" type="email" autocomplete="email" required />
    <div>
        <x-forms.password-field name="password" id="login-password" label="Mật khẩu" autocomplete="current-password" />
        <x-forms.field-error name="password" id="login-password-error" />
    </div>
    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" @checked((string) old('remember', '') === '1') class="accent-black"> Ghi nhớ đăng nhập</label>
    <x-ui.button type="submit" :disabled="! $action" class="w-full">Đăng nhập</x-ui.button>
    @unless ($action)<p class="text-sm text-zinc-500" role="status">Chức năng đăng nhập chưa khả dụng.</p>@endunless
    @if ($forgotUrl)<a href="{{ $forgotUrl }}" class="text-sm underline">Quên mật khẩu?</a>@endif
    @if ($registerUrl)<a href="{{ $registerUrl }}" class="text-sm underline">Tạo tài khoản</a>@endif
</form>
