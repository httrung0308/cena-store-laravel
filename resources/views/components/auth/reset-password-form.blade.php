@props(['action' => null, 'expiresAt' => null, 'expired' => false])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="flex flex-col gap-1" aria-label="Đặt mật khẩu mới">
    @csrf
    <x-auth.input name="password" id="reset-password" label="Mật khẩu mới" type="password" placeholder="Nhập mật khẩu mới..." autocomplete="new-password" required />
    <x-auth.input name="password_confirmation" id="reset-password-confirm" label="Xác nhận mật khẩu" type="password" placeholder="Nhập lại mật khẩu mới..." autocomplete="new-password" required />
    @if ($expiresAt)<p class="text-xs text-zinc-300">Hạn đặt lại: {{ $expiresAt }}</p>@endif
    @if ($expired)<p role="alert">Yêu cầu đã hết hạn.</p>@endif
    <button type="submit" @disabled(! $action || $expired) class="btn mt-1 h-10 rounded-md bg-white py-0 font-semibold text-black hover:bg-zinc-200 disabled:cursor-not-allowed disabled:bg-white/75">Đặt lại mật khẩu</button>
    @unless ($action)<p role="status">Chức năng chưa khả dụng.</p>@endunless
</form>
