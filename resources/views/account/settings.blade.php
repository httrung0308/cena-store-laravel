@extends('layouts.store')

@section('title', 'Cài đặt tài khoản')

@section('content')
    <section class="py-10 sm:px-8">
        <div class="mx-auto flex max-w-4xl flex-col gap-8">
            <div class="space-y-4">
                <a href="{{ route('account.index') }}" class="inline-flex items-center gap-2 font-semibold text-zinc-600 transition hover:text-zinc-900">← Tài khoản của tôi</a>
                <p class="text-sm uppercase tracking-[0.35em] text-zinc-400">Tài khoản</p>
                <h1 class="text-4xl font-bold sm:text-5xl">Cài đặt tài khoản</h1>
                @if (session('status'))<p role="status" class="rounded-3xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</p>@endif
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4 rounded-3xl border border-zinc-200 p-6 shadow-sm">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-lg font-semibold">Thông tin cá nhân</h2>
                    <p class="break-all text-sm text-zinc-500">Email: <span class="text-zinc-900">{{ $user->email }}</span> (không thể đổi)</p>
                    <x-admin.field name="full_name" id="settings-name" label="Họ và tên" :value="$user->full_name" required autocomplete="name" />
                    <x-admin.field name="phone" id="settings-phone" label="Số điện thoại" type="tel" :value="$user->phone" autocomplete="tel" />
                    <button type="submit" class="btn rounded-full px-6 py-3">Lưu thông tin</button>
                </form>

                <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4 rounded-3xl border border-zinc-200 p-6 shadow-sm">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-lg font-semibold">Đổi mật khẩu</h2>
                    <p class="text-sm text-zinc-500">Sau khi đổi, mọi phiên đăng nhập sẽ kết thúc và bạn cần đăng nhập lại.</p>
                    @foreach (['current_password' => ['Mật khẩu hiện tại', 'current-password'], 'password' => ['Mật khẩu mới', 'new-password'], 'password_confirmation' => ['Nhập lại mật khẩu mới', 'new-password']] as $field => [$label, $autocomplete])
                        <div x-data="passwordField()">
                            <label for="settings-{{ $field }}" class="admin-label">{{ $label }}</label>
                            <div class="relative">
                                <input id="settings-{{ $field }}" name="{{ $field }}" type="password" x-bind:type="inputType" autocomplete="{{ $autocomplete }}" required class="input w-full border-zinc-300 pr-24">
                                <button type="button" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold underline" x-on:mousedown.prevent x-on:click="toggle()" x-text="shown ? 'Ẩn' : 'Hiện'">Hiện</button>
                            </div>
                            @error($field)<p role="alert" class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <button type="submit" class="rounded-full border border-zinc-900 px-6 py-3 font-semibold transition hover:bg-zinc-900 hover:text-white">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </section>
@endsection
