@props(['action' => null, 'expiresAt' => null, 'expired' => false, 'attemptsRemaining' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-4" aria-label="Xác nhận mã khôi phục">
    @csrf
    <div>
        <label for="reset-otp" class="block text-xs font-bold uppercase tracking-wider text-muted mb-2 text-center">Mã xác nhận 6 chữ số</label>
        <input id="reset-otp" name="otp" type="text" value="" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required class="input w-full border border-border bg-surface-card text-center text-3xl font-mono tracking-[0.4em] text-main placeholder:text-muted/30 py-3 rounded-2xl focus:border-accent focus:ring-2 focus:ring-accent/20" placeholder="••••••" aria-describedby="reset-otp-help">
        <p id="reset-otp-help" class="mt-2 text-center text-xs text-muted">Nhập đủ 6 chữ số, kể cả số 0 ở đầu.</p>
        <x-forms.field-error name="otp" id="reset-otp-error" />
    </div>
    @if ($expiresAt)<p class="text-center text-xs text-muted">Hạn xác nhận: <span class="font-semibold text-main">{{ $expiresAt }}</span></p>@endif
    @if (is_int($attemptsRemaining))<p class="text-center text-xs text-muted">Lượt còn lại: <span class="font-semibold text-main">{{ $attemptsRemaining }}</span></p>@endif
    <button type="submit" @disabled(! $action || $expired) class="btn w-full py-3 text-sm font-semibold shadow-card disabled:cursor-not-allowed disabled:opacity-40">Xác nhận</button>
    @if ($expired)<div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-3 text-center text-xs font-medium text-error-text">Mã đã hết hạn.</div>@endif
    @unless ($action)<p role="status" class="text-center text-xs text-muted">Chức năng chưa khả dụng.</p>@endunless
</form>
