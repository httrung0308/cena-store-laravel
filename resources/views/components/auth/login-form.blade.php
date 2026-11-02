@props(['action' => null, 'forgotUrl' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="flex w-full flex-col gap-1" aria-label="Đăng nhập">
    @csrf
    <x-auth.input name="email" id="login-email" label="Email" type="email" placeholder="Nhập email..." autocomplete="email" required />
    <x-auth.input name="password" id="login-password" label="Mật khẩu" type="password" placeholder="Nhập mật khẩu..." autocomplete="current-password" required :aside-url="$forgotUrl" aside-label="Quên mật khẩu?" />
    <label class="mb-3 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" @checked((string) old('remember', '') === '1') class="native-checkbox accent-white"> Ghi nhớ đăng nhập</label>
    <button type="submit" @disabled(! $action) class="btn mt-1 h-10 rounded-md bg-white py-0 font-semibold text-black hover:bg-zinc-200 disabled:cursor-not-allowed disabled:bg-white/75">Đăng nhập</button>
    @unless ($action)<p class="text-sm text-zinc-300" role="status">Chức năng đăng nhập chưa khả dụng.</p>@endunless
</form>
