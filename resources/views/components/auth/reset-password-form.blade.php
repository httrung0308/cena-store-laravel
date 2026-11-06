@props(['action' => null, 'expiresAt' => null, 'expired' => false])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="flex flex-col gap-2" aria-label="Đặt mật khẩu mới">
    @csrf
    <x-auth.input name="password" id="reset-password" label="Mật khẩu mới" type="password" placeholder="Nhập mật khẩu mới..." autocomplete="new-password" required />
    <x-auth.input name="password_confirmation" id="reset-password-confirm" label="Xác nhận mật khẩu" type="password" placeholder="Nhập lại mật khẩu mới..." autocomplete="new-password" required />
    @if ($expiresAt)<p class="text-xs text-muted text-center mt-1">Hạn đặt lại: <span class="font-semibold text-main">{{ $expiresAt }}</span></p>@endif
    @if ($expired)<div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-3 text-center text-xs font-medium text-error-text">Yêu cầu đã hết hạn.</div>@endif
    <button type="submit" @disabled(! $action || $expired) class="btn mt-3 w-full py-3 text-sm font-semibold shadow-card disabled:cursor-not-allowed disabled:opacity-40">Đặt lại mật khẩu</button>
    @unless ($action)<p role="status" class="mt-2 text-center text-xs text-muted">Chức năng chưa khả dụng.</p>@endunless
</form>
