@props(['action' => null, 'expiresAt' => null, 'expired' => false, 'attemptsRemaining' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-3" aria-label="Xác nhận mã khôi phục">
    @csrf
    <label for="reset-otp" class="block text-lg font-semibold">Mã xác nhận 6 chữ số</label>
    <input id="reset-otp" name="otp" type="text" value="" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required class="input w-full border-none bg-white/25 text-center text-2xl tracking-[0.5em] text-zinc-100 placeholder:text-zinc-400" placeholder="••••••" aria-describedby="reset-otp-help">
    <p id="reset-otp-help" class="text-xs text-zinc-300">Nhập đủ 6 chữ số, kể cả số 0 ở đầu.</p>
    <x-forms.field-error name="otp" id="reset-otp-error" />
    @if ($expiresAt)<p class="text-xs text-zinc-300">Hạn xác nhận: {{ $expiresAt }}</p>@endif
    @if (is_int($attemptsRemaining))<p class="text-xs text-zinc-300">Lượt còn lại: {{ $attemptsRemaining }}</p>@endif
    <button type="submit" @disabled(! $action || $expired) class="btn h-10 w-full rounded-md bg-white py-0 font-semibold text-black hover:bg-zinc-200 disabled:cursor-not-allowed disabled:bg-white/75">Xác nhận</button>
    @if ($expired)<p role="alert">Mã đã hết hạn.</p>@endif
    @unless ($action)<p role="status">Chức năng chưa khả dụng.</p>@endunless
</form>
