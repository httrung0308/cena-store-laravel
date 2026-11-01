@props(['action' => null, 'state' => 'initial', 'cooldownSeconds' => null, 'message' => null])
@php($state = in_array($state, ['initial', 'submitted', 'cooldown', 'error'], true) ? $state : 'initial')
<section aria-label="Yêu cầu đặt lại mật khẩu" class="space-y-5">
    @if ($state === 'submitted')<p role="status" class="text-sm">Nếu email hợp lệ, hướng dẫn khôi phục sẽ được gửi tới hộp thư.</p>@endif
    @if ($state === 'cooldown')<p role="status" class="text-sm">Vui lòng chờ @if (is_int($cooldownSeconds) && $cooldownSeconds > 0) {{ $cooldownSeconds }} giây @endif trước khi gửi lại.</p>@endif
    @if ($state === 'error')<p role="alert" class="text-sm text-red-500">{{ $message ?? 'Không thể gửi yêu cầu. Vui lòng thử lại.' }}</p>@endif
    <form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-4">
        @csrf
        <x-forms.text-field name="email" id="reset-request-email" label="Email" type="email" autocomplete="email" required />
        <x-ui.button type="submit" :disabled="! $action || $state === 'cooldown'">Yêu cầu khôi phục</x-ui.button>
        @unless ($action)<p class="text-sm text-zinc-500">Chức năng chưa khả dụng.</p>@endunless
    </form>
</section>
