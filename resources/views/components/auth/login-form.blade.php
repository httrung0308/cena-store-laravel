@props(['action' => null, 'forgotUrl' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="flex w-full flex-col gap-2" aria-label="Đăng nhập">
    @csrf
    <x-auth.input name="email" id="login-email" label="Email" type="email" placeholder="Nhập email..." autocomplete="email" required />
    <x-auth.input name="password" id="login-password" label="Mật khẩu" type="password" placeholder="Nhập mật khẩu..." autocomplete="current-password" required :aside-url="$forgotUrl" aside-label="Quên mật khẩu?" />
    <label class="my-2 inline-flex items-center gap-2 text-xs font-medium text-muted cursor-pointer select-none">
        <input type="checkbox" name="remember" value="1" @checked((string) old('remember', '') === '1') class="native-checkbox text-accent focus:ring-accent rounded">
        <span>Ghi nhớ đăng nhập</span>
    </label>
    <button type="submit" @disabled(! $action) class="btn mt-2 w-full py-3 text-sm font-semibold shadow-card disabled:cursor-not-allowed disabled:opacity-40">Đăng nhập</button>
    @unless ($action)<p class="mt-2 text-center text-xs text-muted" role="status">Chức năng đăng nhập chưa khả dụng.</p>@endunless
</form>
