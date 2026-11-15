@extends('layouts.admin')
@section('title', 'Tạo Tài Khoản Staff / Admin')
@section('eyebrow', 'QUẢN TRỊ HỆ THỐNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.users.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
    <div class="max-w-3xl mx-auto">
        <form action="{{ route('admin.users.store') }}" method="POST" class="admin-card space-y-5"
              x-data="{ submitting: false }"
              @submit="if (submitting) { $event.preventDefault(); return false; } submitting = true">
            @csrf

            <div class="space-y-1">
                <h2 class="text-base font-bold text-main">Thông tin tài khoản nội bộ</h2>
                <p class="text-xs text-muted">Tạo mới tài khoản Nhân viên hoặc Quản trị viên. Tài khoản sẽ được kích hoạt ngay sau khi tạo.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.field name="full_name" label="Họ và tên" required />

                <div>
                    <label for="admin-role" class="admin-label">Vai trò <span class="text-error">*</span></label>
                    <select id="admin-role" name="role" required aria-required="true"
                            aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}"
                            @if ($errors->has('role')) aria-describedby="admin-role-error" @endif
                            class="input w-full {{ $errors->has('role') ? 'border-error focus:border-error focus:ring-2 focus:ring-error/20 ring-1 ring-error' : 'border-border focus:border-accent' }}">
                        @foreach ([App\Enums\RoleSlug::Staff, App\Enums\RoleSlug::Admin] as $role)
                            <option value="{{ $role->value }}" @selected(old('role', App\Enums\RoleSlug::Staff->value) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @if ($errors->has('role'))
                        <p id="admin-role-error" role="alert" class="mt-1 text-xs font-medium text-error">{{ $errors->first('role') }}</p>
                    @endif
                </div>

                <x-admin.field name="email" label="Email" type="email" required />
                <x-admin.field name="phone" label="Số điện thoại" type="tel" hint="Ví dụ: 0901234567" />

                <x-admin.field name="password" label="Mật khẩu" type="password" autocomplete="new-password" required hint="Từ 8 ký tự trở lên; tránh chuỗi quá dài vượt giới hạn 72 byte khi mã hóa." />
                <x-admin.field name="password_confirmation" label="Nhập lại mật khẩu" type="password" autocomplete="new-password" required />
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" :disabled="submitting" class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-2">
                    <span x-show="!submitting">Tạo tài khoản</span>
                    <span x-cloak x-show="submitting" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang tạo...</span>
                </button>
                <a href="{{ route('admin.users.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Hủy</a>
            </div>
        </form>
    </div>
@endsection
