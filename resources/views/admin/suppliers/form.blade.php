@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa Nhà Cung Cấp' : 'Thêm Nhà Cung Cấp')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.suppliers.index') }}">← Quay lại danh sách</a>
@endsection
@php
    $action = $record->exists ? route('admin.suppliers.update', $record) : route('admin.suppliers.store');
    $method = $record->exists ? 'PUT' : 'POST';
    $supplier = $record->toArray();
    $isNew = empty($supplier['id']);
    $initialCode = mb_strtoupper((string) old('code', $supplier['code'] ?? ''));
    $hasCodeError = $errors->has('code');
    $openCodeEditor = $hasCodeError || ($isNew && $initialCode !== '');
@endphp
@section('content')
    <div class="max-w-3xl mx-auto">
        <form action="{{ $action }}" method="POST" class="admin-card space-y-5"
              x-data="{
                  isNew: {{ $isNew ? 'true' : 'false' }},
                  code: @js($initialCode),
                  showCodeEditor: {{ $openCodeEditor ? 'true' : 'false' }},
                  isCodeLocked: {{ $isNew ? 'false' : 'true' }},
                  unlockCode() {
                      this.isCodeLocked = false;
                      this.$nextTick(() => {
                          const el = this.$refs.codeInput;
                          if (el) {
                              el.focus();
                              el.select();
                          }
                      });
                  }
              }">
            @csrf
            @if ($method === 'PUT') @method('PUT') @endif

            <h2 class="text-base font-bold text-main">Thông tin nhà cung cấp</h2>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-admin.field name="name" label="Tên nhà cung cấp" :value="$supplier['name'] ?? ''" required />

                    {{-- Xem trước mã nhà cung cấp + vùng tùy chỉnh --}}
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted">
                        <span class="truncate">
                            Mã nhà cung cấp:
                            @if ($isNew)
                                <template x-if="!code">
                                    <span class="font-medium text-main">Tự động sinh mã nội bộ duy nhất</span>
                                </template>
                                <template x-if="code">
                                    <code class="font-mono font-bold text-main" x-text="code"></code>
                                </template>
                            @else
                                <code class="font-mono font-bold text-main" x-text="code || @js($supplier['code'] ?? '')"></code>
                            @endif
                        </span>
                        <button type="button" @click="showCodeEditor = !showCodeEditor"
                                class="font-semibold text-accent hover:underline shrink-0">
                            <span x-text="showCodeEditor ? 'Ẩn tùy chỉnh mã' : 'Tùy chỉnh mã'"></span>
                        </button>
                    </div>

                    <div x-show="showCodeEditor" x-cloak class="mt-2.5 rounded-xl border border-border bg-surface/60 p-3 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="supplier-code" class="text-xs font-semibold text-main">
                                Mã nhà cung cấp
                                @unless ($isNew)<span class="text-error">*</span>@endunless
                            </label>
                            <button type="button" x-show="!isNew && isCodeLocked" @click="unlockCode()"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-accent hover:underline">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <span>Mở khóa sửa mã</span>
                            </button>
                        </div>
                        <input id="supplier-code" name="code" type="text" x-ref="codeInput" x-model="code"
                               @input="code = $event.target.value.toUpperCase()"
                               :readonly="isCodeLocked"
                               :required="!isNew"
                               maxlength="50"
                               :class="[isCodeLocked ? 'bg-surface-alt/70 cursor-not-allowed opacity-85' : 'bg-surface', @js($hasCodeError) ? 'border-error ring-1 ring-error' : 'border-border focus:border-accent']"
                               class="input w-full text-main font-mono uppercase text-xs"
                               placeholder="Để trống để tự động sinh (VD: SUP-0001)">
                        <p class="text-[11px] text-muted">
                            <span x-show="isNew">Để trống để tự động sinh mã (ví dụ: SUP-0001). Chỉ gồm chữ, số, dấu gạch ngang hoặc gạch dưới.</span>
                            <span x-show="!isNew && isCodeLocked">Đang khóa để giữ nguyên mã đã lưu khi sửa tên nhà cung cấp.</span>
                            <span x-show="!isNew && !isCodeLocked" class="text-warning-text font-medium">Lưu ý: Đổi mã sẽ cập nhật mã hiển thị trên các phiếu nhập kho của nhà cung cấp này.</span>
                        </p>
                        <x-forms.field-error name="code" />
                    </div>
                </div>

                <x-admin.field name="phone" label="Số điện thoại" :value="$supplier['phone'] ?? ''" type="tel" required />
                <x-admin.field name="email" label="Email" :value="$supplier['email'] ?? ''" type="email" />
                <div class="md:col-span-2"><x-admin.field name="address" label="Địa chỉ" :value="$supplier['address'] ?? ''" /></div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                <input type="checkbox" name="is_active" value="1" class="native-checkbox accent-accent rounded" @checked(old('is_active', $supplier['is_active'] ?? true))>
                <span>Đang hợp tác với nhà cung cấp này</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md">Lưu nhà cung cấp</button>
                <a href="{{ route('admin.suppliers.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Hủy</a>
            </div>
        </form>
    </div>
@endsection
