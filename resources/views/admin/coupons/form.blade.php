@extends('layouts.admin')
@section('title', $coupon->exists ? 'Sửa coupon' : 'Tạo coupon')
@section('eyebrow', 'QUẢN LÝ MÃ GIẢM GIÁ')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.coupons.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
@php
    $initialType = old('type', $coupon->type?->value ?? 'PERCENTAGE');
    $initialValue = old('value', $coupon->value ?? '');
    $initialMinOrder = old('min_order_amount', $coupon->min_order_amount ?? 0);
    $initialMaxDiscount = old('max_discount_amount', $coupon->max_discount_amount ?? '');
@endphp
<div class="max-w-3xl">
    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}"
          class="admin-card space-y-6"
          x-data="{
              type: @js($initialType),
              rawValue: @js((string) $initialValue),
              displayValue: '',
              minOrderRaw: @js((string) $initialMinOrder),
              minOrderDisplay: '',
              maxDiscountRaw: @js((string) $initialMaxDiscount),
              maxDiscountDisplay: '',
              formatNum(val) {
                  const clean = String(val ?? '').replace(/[^0-9]/g, '');
                  if (!clean) return '';
                  return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
              },
              init() {
                  this.syncType();
                  this.minOrderDisplay = this.formatNum(this.minOrderRaw);
                  this.maxDiscountDisplay = this.formatNum(this.maxDiscountRaw);
              },
              syncType() {
                  if (this.type === 'FREE_SHIPPING') {
                      this.rawValue = '0';
                      this.displayValue = '0';
                  } else if (this.type === 'PERCENTAGE') {
                      this.displayValue = this.rawValue;
                  } else {
                      this.displayValue = this.formatNum(this.rawValue);
                  }
              },
              onTypeChange() {
                  this.syncType();
              },
              onValueInput(e) {
                  const clean = String(e.target.value).replace(/[^0-9]/g, '');
                  if (this.type === 'PERCENTAGE') {
                      let num = parseInt(clean || '0', 10);
                      if (num > 100) num = 100;
                      this.rawValue = clean ? String(num) : '';
                      this.displayValue = this.rawValue;
                  } else if (this.type === 'FIXED_DISCOUNT') {
                      this.rawValue = clean;
                      this.displayValue = this.formatNum(clean);
                  }
              },
              onMinOrderInput(e) {
                  const clean = String(e.target.value).replace(/[^0-9]/g, '');
                  this.minOrderRaw = clean;
                  this.minOrderDisplay = this.formatNum(clean);
              },
              onMaxDiscountInput(e) {
                  const clean = String(e.target.value).replace(/[^0-9]/g, '');
                  this.maxDiscountRaw = clean;
                  this.maxDiscountDisplay = this.formatNum(clean);
              }
          }">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif

        <div>
            <h2 class="text-base font-bold text-main">Thông tin chương trình</h2>
            <p class="text-xs text-muted mt-1">Cấu hình mã giảm giá, hình thức áp dụng và điều kiện ràng buộc.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="coupon-code" class="admin-label">Mã coupon <span class="text-error">*</span></label>
                <input id="coupon-code" name="code" type="text" value="{{ old('code', $coupon->code) }}"
                       class="input w-full border-border focus:border-accent text-main font-mono uppercase text-xs @error('code') border-error @enderror"
                       placeholder="Ví dụ: SALE50, FREESHIP" required maxlength="50">
                <p class="mt-1 text-[11px] text-muted">Mã chỉ gồm chữ cái, số và gạch ngang.</p>
                <x-forms.field-error name="code" />
            </div>

            <div>
                <label for="coupon-name" class="admin-label">Tên chương trình <span class="text-error">*</span></label>
                <input id="coupon-name" name="name" type="text" value="{{ old('name', $coupon->name) }}"
                       class="input w-full border-border focus:border-accent text-main text-xs @error('name') border-error @enderror"
                       placeholder="Ví dụ: Giảm giá mùa hè, Miễn phí vận chuyển" required maxlength="150">
                <x-forms.field-error name="name" />
            </div>
        </div>

        <div>
            <label class="admin-label" for="coupon-type">Loại giảm giá <span class="text-error">*</span></label>
            <select id="coupon-type" name="type" x-model="type" x-on:change="onTypeChange"
                    class="input w-full border-border bg-surface text-main text-xs" required>
                @foreach ($types as $typeOption)
                    <option value="{{ $typeOption->value }}" @selected(old('type', $coupon->type?->value) === $typeOption->value)>
                        {{ $typeOption->label() }}
                    </option>
                @endforeach
            </select>
            <x-forms.field-error name="type" />
        </div>

        {{-- Conditional Discount Value --}}
        <div class="rounded-xl border border-border/70 p-4 bg-surface-alt/40 space-y-4">
            <template x-if="type === 'FREE_SHIPPING'">
                <div>
                    <label class="admin-label">Giá trị giảm</label>
                    <div class="input w-full border-border bg-surface/70 text-muted text-xs flex items-center justify-between">
                        <span>Miễn phí vận chuyển toàn phần (100% phí ship)</span>
                        <span class="font-mono font-bold text-accent">0 ₫</span>
                    </div>
                    <input type="hidden" name="value" value="0">
                    <p class="mt-1 text-[11px] text-muted">Hệ thống tự động trừ toàn bộ phí vận chuyển khi thỏa điều kiện đơn tối thiểu.</p>
                </div>
            </template>

            <template x-if="type === 'PERCENTAGE'">
                <div>
                    <label for="coupon-val-percent" class="admin-label">Phần trăm giảm (%) <span class="text-error">*</span></label>
                    <div class="relative mt-1">
                        <input id="coupon-val-percent" type="number" min="1" max="100" x-model="displayValue" x-on:input="onValueInput"
                               class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-8 @error('value') border-error @enderror"
                               placeholder="Ví dụ: 10, 20, 50" required>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none">%</span>
                    </div>
                    <input type="hidden" name="value" x-bind:value="rawValue">
                    <p class="mt-1 text-[11px] text-muted">Nhập từ 1 đến 100.</p>
                    <x-forms.field-error name="value" />
                </div>
            </template>

            <template x-if="type === 'FIXED_DISCOUNT'">
                <div>
                    <label for="coupon-val-fixed" class="admin-label">Số tiền giảm cố định (₫) <span class="text-error">*</span></label>
                    <div class="relative mt-1">
                        <input id="coupon-val-fixed" type="text" inputmode="numeric" x-model="displayValue" x-on:input="onValueInput"
                               class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-8 @error('value') border-error @enderror"
                               placeholder="Ví dụ: 50.000, 100.000" required>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none">₫</span>
                    </div>
                    <input type="hidden" name="value" x-bind:value="rawValue">
                    <p class="mt-1 text-[11px] text-muted">Số tiền sẽ được trừ trực tiếp vào tổng tiền hàng.</p>
                    <x-forms.field-error name="value" />
                </div>
            </template>

            {{-- Max Discount Amount (Only for PERCENTAGE) --}}
            <div x-show="type === 'PERCENTAGE'" x-transition>
                <label for="coupon-max-discount" class="admin-label">Mức giảm tối đa (₫, tùy chọn)</label>
                <div class="relative mt-1">
                    <input id="coupon-max-discount" type="text" inputmode="numeric" x-model="maxDiscountDisplay" x-on:input="onMaxDiscountInput"
                           class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-8 @error('max_discount_amount') border-error @enderror"
                           placeholder="Ví dụ: 200.000">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none">₫</span>
                </div>
                <input type="hidden" name="max_discount_amount" x-bind:value="maxDiscountRaw">
                <p class="mt-1 text-[11px] text-muted">Giới hạn số tiền giảm tối đa để tránh thâm hụt với đơn hàng giá trị cao. Để trống = không giới hạn.</p>
                <x-forms.field-error name="max_discount_amount" />
            </div>
        </div>

        {{-- Minimum Order Amount --}}
        <div>
            <label for="coupon-min-order" class="admin-label">Giá trị đơn hàng tối thiểu (₫) <span class="text-error">*</span></label>
            <div class="relative mt-1">
                <input id="coupon-min-order" type="text" inputmode="numeric" x-model="minOrderDisplay" x-on:input="onMinOrderInput"
                       class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-8 @error('min_order_amount') border-error @enderror"
                       placeholder="Ví dụ: 500.000" required>
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none">₫</span>
            </div>
            <input type="hidden" name="min_order_amount" x-bind:value="minOrderRaw">
            <p class="mt-1 text-[11px] text-muted">Đơn hàng phải đạt giá trị này mới áp dụng được mã. Nhập 0 nếu không yêu cầu.</p>
            <x-forms.field-error name="min_order_amount" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="usage_limit" label="Tổng lượt dùng tối đa" type="number" min="1" :value="$coupon->usage_limit" hint="Để trống nếu không giới hạn tổng số lần dùng." />
            <x-admin.field name="per_user_limit" label="Lượt dùng tối đa mỗi khách" type="number" min="1" :value="$coupon->per_user_limit ?? 1" required hint="Mặc định mỗi tài khoản được dùng 1 lần." />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="starts_at" label="Thời gian bắt đầu" type="datetime-local" :value="$coupon->starts_at?->setTimezone(config('store.business_timezone'))->format('Y-m-d\TH:i')" hint="Để trống nếu áp dụng ngay lập tức." />
            <x-admin.field name="expires_at" label="Thời gian kết thúc" type="datetime-local" :value="$coupon->expires_at?->setTimezone(config('store.business_timezone'))->format('Y-m-d\TH:i')" hint="Để trống nếu không có hạn kết thúc." />
        </div>

        <label class="flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
            <input type="checkbox" name="is_active" value="1" class="native-checkbox text-accent focus:ring-accent rounded" @checked(old('is_active', $coupon->exists ? $coupon->is_active : true))>
            <span>Kích hoạt mã coupon ngay</span>
        </label>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-xs text-error font-medium">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex items-center gap-3 pt-2">
            <button class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md" type="submit">
                {{ $coupon->exists ? 'Lưu thay đổi' : 'Tạo mã coupon' }}
            </button>
            <a href="{{ route('admin.coupons.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Hủy</a>
        </div>
    </form>
</div>
@endsection
