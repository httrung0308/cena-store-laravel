@extends('layouts.store')

@section('title', 'Cài đặt tài khoản')

@section('content')
    <section class="py-6 sm:py-10">
        <div class="mx-auto flex max-w-5xl flex-col gap-8">
            <div class="space-y-3">
                <a href="{{ route('account.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-accent">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Tài khoản của tôi</span>
                </a>
                <h1 class="text-2xl sm:text-4xl font-extrabold text-main tracking-tight">Cài đặt tài khoản</h1>
                @if (session('status'))
                    <p role="status" class="rounded-xl border border-success/30 bg-success-bg p-4 text-sm font-medium text-success-text shadow-subtle">{{ session('status') }}</p>
                @endif
            </div>

            <div class="grid gap-8 md:grid-cols-2">
                <form method="POST" action="{{ route('account.profile.update') }}" class="flex flex-col justify-between space-y-5 rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-8">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-4">
                        <h2 class="text-xl font-bold text-main">Thông tin cá nhân</h2>
                        <p class="break-all text-xs text-muted">Email: <span class="font-medium text-main">{{ $user->email }}</span> (không thể đổi)</p>
                        <x-admin.field name="full_name" id="settings-name" label="Họ và tên" :value="$user->full_name" required autocomplete="name" />
                        <x-admin.field name="phone" id="settings-phone" label="Số điện thoại" type="tel" :value="$user->phone" autocomplete="tel" />
                    </div>
                    <div class="pt-4">
                        <button type="submit" class="btn w-full py-3 text-sm font-semibold">Lưu thông tin</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('account.password.update') }}" class="flex flex-col justify-between space-y-5 rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-8">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-4">
                        <h2 class="text-xl font-bold text-main">Đổi mật khẩu</h2>
                        <p class="text-xs text-muted leading-relaxed">Sau khi đổi, mọi phiên đăng nhập sẽ kết thúc và bạn cần đăng nhập lại.</p>
                        @foreach (['current_password' => ['Mật khẩu hiện tại', 'current-password'], 'password' => ['Mật khẩu mới', 'new-password'], 'password_confirmation' => ['Nhập lại mật khẩu mới', 'new-password']] as $field => [$label, $autocomplete])
                            <div x-data="passwordField()" class="space-y-1.5">
                                <label for="settings-{{ $field }}" class="text-xs font-bold uppercase tracking-wider text-muted">{{ $label }}</label>
                                <div class="relative">
                                    <input id="settings-{{ $field }}" name="{{ $field }}" type="password" x-bind:type="inputType" autocomplete="{{ $autocomplete }}" required class="input w-full pr-16 text-sm">
                                    <button type="button" class="btn-icon absolute inset-y-0 right-1 my-auto h-8 px-2.5 text-xs font-semibold text-muted hover:text-accent" x-on:mousedown.prevent x-on:click="toggle()" x-text="shown ? 'Ẩn' : 'Hiện'">Hiện</button>
                                </div>
                                @error($field)<p role="alert" class="mt-1 text-xs font-medium text-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="pt-4">
                        <button type="submit" class="btn-secondary w-full py-3 text-sm font-semibold hover:border-accent hover:text-accent">Đổi mật khẩu</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
