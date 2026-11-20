@props(['action' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="flex w-full flex-col gap-2" aria-label="Đăng ký">
    @csrf
    <x-auth.input name="full_name" id="register-name" label="Họ và tên" placeholder="Nhập họ và tên..." autocomplete="name" required />
    <x-auth.input name="email" id="register-email" label="Email" type="email" placeholder="Nhập email..." autocomplete="email" required />
    <x-auth.input name="phone" id="register-phone" label="Số điện thoại (không bắt buộc)" type="tel" placeholder="Nhập số điện thoại..." autocomplete="tel" />
    <x-auth.input name="password" id="register-password" label="Mật khẩu" type="password" placeholder="Nhập mật khẩu..." autocomplete="new-password" required />
    <x-auth.input name="password_confirmation" id="register-confirm" label="Xác nhận mật khẩu" type="password" placeholder="Nhập lại mật khẩu..." autocomplete="new-password" required />
    <button type="submit" @disabled(! $action) class="btn mt-3 w-full py-3 text-sm font-semibold shadow-card disabled:cursor-not-allowed disabled:opacity-40">Đăng ký</button>
    @unless ($action)<p role="status" class="mt-2 text-center text-xs text-muted">Đăng ký chưa khả dụng.</p>@endunless
</form>
