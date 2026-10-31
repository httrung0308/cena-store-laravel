@props(['action' => null, 'expiresAt' => null, 'expired' => false, 'attemptsRemaining' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-4" aria-label="Xác nhận mã khôi phục">
    @csrf
    <label for="reset-otp" class="block text-lg font-semibold">Mã xác nhận 6 chữ số</label>
    <input id="reset-otp" name="otp" type="text" value="" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required class="input w-full tracking-widest" aria-describedby="reset-otp-help">
    <p id="reset-otp-help" class="text-xs text-zinc-500">Nhập đủ 6 chữ số, kể cả số 0 ở đầu.</p>
    <x-forms.field-error name="otp" id="reset-otp-error" />
    @if ($expiresAt)<p class="text-xs text-zinc-600">Hạn xác nhận: {{ $expiresAt }}</p>@endif
    @if (is_int($attemptsRemaining))<p class="text-xs">Lượt còn lại: {{ $attemptsRemaining }}</p>@endif
    <x-ui.button type="submit" :disabled="! $action || $expired">Xác nhận</x-ui.button>
    @if ($expired)<p role="alert">Mã đã hết hạn.</p>@endif
    @unless ($action)<p role="status">Chức năng chưa khả dụng.</p>@endunless
</form>
