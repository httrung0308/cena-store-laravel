@props(['action' => null, 'state' => 'initial', 'cooldownSeconds' => null, 'message' => null])
@php($state = in_array($state, ['initial', 'submitted', 'cooldown', 'error'], true) ? $state : 'initial')
<section aria-label="Yêu cầu đặt lại mật khẩu" class="space-y-4">
    @if ($state === 'submitted')
        <div role="status" class="rounded-xl border border-success/30 bg-success-bg p-4 text-xs font-medium text-success-text shadow-subtle leading-relaxed">Nếu email hợp lệ, hướng dẫn khôi phục sẽ được gửi tới hộp thư.</div>
    @endif
    @if ($state === 'cooldown')
        <div role="status" class="rounded-xl border border-warning/30 bg-warning-bg p-4 text-xs font-medium text-warning-text shadow-subtle leading-relaxed">Vui lòng chờ @if (is_int($cooldownSeconds) && $cooldownSeconds > 0) {{ $cooldownSeconds }} giây @endif trước khi gửi lại.</div>
    @endif
    @if ($state === 'error')
        <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-xs font-medium text-error-text shadow-subtle leading-relaxed">{{ $message ?? 'Không thể gửi yêu cầu. Vui lòng thử lại.' }}</div>
    @endif
    <form @if ($action) action="{{ $action }}" @endif method="POST" class="flex flex-col gap-2">
        @csrf
        <x-auth.input name="email" id="reset-request-email" label="Email" type="email" placeholder="Nhập email đã đăng ký..." autocomplete="email" required />
        <button type="submit" @disabled(! $action || $state === 'cooldown') class="btn mt-3 w-full py-3 text-sm font-semibold shadow-card disabled:cursor-not-allowed disabled:opacity-40">Gửi mã xác nhận</button>
        @unless ($action)<p class="mt-2 text-center text-xs text-muted">Chức năng chưa khả dụng.</p>@endunless
    </form>
</section>
