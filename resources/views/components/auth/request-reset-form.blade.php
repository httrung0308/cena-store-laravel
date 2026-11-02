@props(['action' => null, 'state' => 'initial', 'cooldownSeconds' => null, 'message' => null])
@php($state = in_array($state, ['initial', 'submitted', 'cooldown', 'error'], true) ? $state : 'initial')
<section aria-label="Yêu cầu đặt lại mật khẩu" class="space-y-5">
    @if ($state === 'submitted')<p role="status" class="text-sm">Nếu email hợp lệ, hướng dẫn khôi phục sẽ được gửi tới hộp thư.</p>@endif
    @if ($state === 'cooldown')<p role="status" class="text-sm">Vui lòng chờ @if (is_int($cooldownSeconds) && $cooldownSeconds > 0) {{ $cooldownSeconds }} giây @endif trước khi gửi lại.</p>@endif
    @if ($state === 'error')<p role="alert" class="text-sm text-red-300">{{ $message ?? 'Không thể gửi yêu cầu. Vui lòng thử lại.' }}</p>@endif
    <form @if ($action) action="{{ $action }}" @endif method="POST" class="flex flex-col gap-1">
        @csrf
        <x-auth.input name="email" id="reset-request-email" label="Email" type="email" placeholder="Nhập email đã đăng ký..." autocomplete="email" required />
        <button type="submit" @disabled(! $action || $state === 'cooldown') class="btn mt-1 h-10 rounded-md bg-white py-0 font-semibold text-black hover:bg-zinc-200 disabled:cursor-not-allowed disabled:bg-white/75">Gửi mã xác nhận</button>
        @unless ($action)<p class="text-sm text-zinc-300">Chức năng chưa khả dụng.</p>@endunless
    </form>
</section>
