@props(['action' => null])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="flex w-full flex-col gap-1" aria-label="Đăng ký">
    @csrf
    <x-auth.input name="full_name" id="register-name" label="Họ và tên" placeholder="Nhập họ và tên..." autocomplete="name" required />
    <x-auth.input name="email" id="register-email" label="Email" type="email" placeholder="Nhập email..." autocomplete="email" required />
    <x-auth.input name="phone" id="register-phone" label="Số điện thoại (không bắt buộc)" type="tel" placeholder="Nhập số điện thoại..." autocomplete="tel" />
    <x-auth.input name="password" id="register-password" label="Mật khẩu" type="password" placeholder="Nhập mật khẩu..." autocomplete="new-password" required />
    <x-auth.input name="password_confirmation" id="register-confirm" label="Xác nhận mật khẩu" type="password" placeholder="Nhập lại mật khẩu..." autocomplete="new-password" required />
    <button type="submit" @disabled(! $action) class="btn mt-1 h-10 rounded-md bg-white py-0 font-semibold text-black hover:bg-zinc-200 disabled:cursor-not-allowed disabled:bg-white/75">Đăng ký</button>
    @unless ($action)<p role="status" class="text-sm text-zinc-300">Đăng ký chưa khả dụng.</p>@endunless
</form>
