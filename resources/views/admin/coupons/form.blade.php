@extends('layouts.admin')
@section('title', $coupon->exists ? 'Sửa coupon' : 'Tạo coupon')
@section('eyebrow', 'QUẢN LÝ MÃ GIẢM GIÁ')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.coupons.index') }}">← Quay lại danh sách</a>
@endsection
@section('content')
@php
    $initialCode = mb_strtoupper((string) old('code', $coupon->code ?? ''));
    $initialType = old('type', $coupon->type?->value ?? 'PERCENTAGE');
    $initialValue = old('value', $coupon->value ?? '');
    $initialMinOrder = old('min_order_amount', $coupon->min_order_amount ?? 0);
    $initialMaxDiscount = old('max_discount_amount', $coupon->max_discount_amount ?? '');
    $initialUsageLimit = old('usage_limit', $coupon->usage_limit ?? '');
    $initialPerUserLimit = old('per_user_limit', $coupon->per_user_limit ?? 1);
    $initialStartsAt = old('starts_at', $coupon->starts_at?->setTimezone(config('store.business_timezone'))->format('Y-m-d\TH:i') ?? '');
    $initialExpiresAt = old('expires_at', $coupon->expires_at?->setTimezone(config('store.business_timezone'))->format('Y-m-d\TH:i') ?? '');
    $initialActive = (bool) old('is_active', $coupon->exists ? $coupon->is_active : true);
@endphp
<div class="max-w-5xl mx-auto">
    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}"
          class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
          x-data="{
              code: @js($initialCode),
              type: @js($initialType),
              rawValue: @js((string) $initialValue),
              displayValue: '',
              minOrderRaw: @js((string) $initialMinOrder),
              minOrderDisplay: '',
              maxDiscountRaw: @js((string) $initialMaxDiscount),
              maxDiscountDisplay: '',
              usageLimit: @js((string) $initialUsageLimit),
              perUserLimit: @js((string) $initialPerUserLimit),
              startsAt: @js((string) $initialStartsAt),
              expiresAt: @js((string) $initialExpiresAt),
              isActive: @js($initialActive),
              formatNum(val) {
                  const clean = String(val ?? '').replace(/[^0-9]/g, '');
                  if (!clean) return '';
                  return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
              },
              formatDateTime(val) {
                  if (!val) return '';
                  const [datePart, timePart] = String(val).split('T');
                  if (!datePart) return val;
                  const [y, m, d] = datePart.split('-');
                  return `${d}/${m}/${y}` + (timePart ? ` ${timePart}` : '');
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
        <input type="hidden" name="value" x-bind:value="type === 'FREE_SHIPPING' ? '0' : rawValue">

        <div class="admin-card space-y-6">
            <div>
                <h2 class="text-base font-bold text-main">Thông tin chương trình</h2>
                <p class="text-xs text-muted mt-1">Cấu hình mã giảm giá, hình thức áp dụng và điều kiện ràng buộc.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-12">
                <div class="sm:col-span-5">
                    <label for="coupon-code" class="admin-label">Mã coupon <span class="text-error">*</span></label>
                    <input id="coupon-code" name="code" type="text" x-model="code"
                           @input="code = $event.target.value.toUpperCase()"
                           class="input w-full border-border focus:border-accent text-main font-mono uppercase text-xs @error('code') border-error @enderror"
                           placeholder="Ví dụ: SALE50, FREESHIP" required maxlength="50">
                    <p class="mt-1 text-[11px] text-muted">Tự chuyển thành chữ in hoa (chữ, số, dấu gạch ngang/dưới).</p>
                    <x-forms.field-error name="code" />
                </div>

                <div class="sm:col-span-7">
                    <label for="coupon-name" class="admin-label">Tên chương trình <span class="text-error">*</span></label>
                    <input id="coupon-name" name="name" type="text" value="{{ old('name', $coupon->name) }}"
                           class="input w-full border-border focus:border-accent text-main text-xs @error('name') border-error @enderror"
                           placeholder="Ví dụ: Giảm giá mùa hè, Miễn phí vận chuyển" required maxlength="150">
                    <x-forms.field-error name="name" />
                </div>
            </div>

            {{-- Hình thức, mức giảm & điều kiện đơn tối thiểu --}}
            <div class="rounded-xl border border-border/70 p-4 bg-surface-alt/40 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
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

                    <template x-if="type === 'FREE_SHIPPING'">
                        <div>
                            <label class="admin-label">Giá trị giảm</label>
                            <div class="input w-full border-border bg-surface/70 text-muted text-xs flex items-center justify-between">
                                <span>Miễn 100% phí vận chuyển</span>
                                <span class="font-mono font-bold text-accent">0 ₫</span>
                            </div>
                            <p class="mt-1 text-[11px] text-muted">Miễn toàn bộ phí vận chuyển khi đạt đơn tối thiểu.</p>
                        </div>
                    </template>

                    <template x-if="type === 'PERCENTAGE'">
                        <div>
                            <label for="coupon-val-percent" class="admin-label">Phần trăm giảm (%) <span class="text-error">*</span></label>
                            <div class="relative">
                                <input id="coupon-val-percent" type="number" min="1" max="100" x-model="displayValue" x-on:input="onValueInput"
                                       class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-8 @error('value') border-error @enderror"
                                       placeholder="Ví dụ: 10, 20, 50" required>
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none">%</span>
                            </div>
                            <p class="mt-1 text-[11px] text-muted">Nhập từ 1 đến 100.</p>
                            <x-forms.field-error name="value" />
                        </div>
                    </template>

                    <template x-if="type === 'FIXED_DISCOUNT'">
                        <div>
                            <label for="coupon-val-fixed" class="admin-label">Số tiền giảm cố định (₫) <span class="text-error">*</span></label>
                            <div class="relative">
                                <input id="coupon-val-fixed" type="text" inputmode="numeric" x-model="displayValue" x-on:input="onValueInput"
                                       class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-8 @error('value') border-error @enderror"
                                       placeholder="Ví dụ: 50.000, 100.000" required>
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none">₫</span>
                            </div>
                            <p class="mt-1 text-[11px] text-muted">Trừ trực tiếp vào tổng tiền hàng.</p>
                            <x-forms.field-error name="value" />
                        </div>
                    </template>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Max Discount Amount (Only for PERCENTAGE) --}}
                    <div x-show="type === 'PERCENTAGE'" x-transition>
                        <label for="coupon-max-discount" class="admin-label">Mức giảm tối đa (₫, tùy chọn)</label>
                        <div class="relative">
                            <input id="coupon-max-discount" type="text" inputmode="numeric" x-model="maxDiscountDisplay" x-on:input="onMaxDiscountInput"
                                   class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-8 @error('max_discount_amount') border-error @enderror"
                                   placeholder="Ví dụ: 200.000">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none">₫</span>
                        </div>
                        <input type="hidden" name="max_discount_amount" x-bind:value="maxDiscountRaw">
                        <p class="mt-1 text-[11px] text-muted">Để trống nếu không giới hạn mức giảm tối đa.</p>
                        <x-forms.field-error name="max_discount_amount" />
                    </div>

                    {{-- Minimum Order Amount --}}
                    <div :class="type !== 'PERCENTAGE' ? 'sm:col-span-2' : ''">
                        <label for="coupon-min-order" class="admin-label">Giá trị đơn hàng tối thiểu (₫) <span class="text-error">*</span></label>
                        <div class="relative">
                            <input id="coupon-min-order" type="text" inputmode="numeric" x-model="minOrderDisplay" x-on:input="onMinOrderInput"
                                   class="input w-full border-border focus:border-accent text-main font-mono text-xs pr-8 @error('min_order_amount') border-error @enderror"
                                   placeholder="Ví dụ: 500.000" required>
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none">₫</span>
                        </div>
                        <input type="hidden" name="min_order_amount" x-bind:value="minOrderRaw">
                        <p class="mt-1 text-[11px] text-muted">Nhập 0 nếu áp dụng cho mọi đơn hàng.</p>
                        <x-forms.field-error name="min_order_amount" />
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="coupon-usage-limit" class="admin-label">Tổng lượt dùng tối đa</label>
                    <input id="coupon-usage-limit" name="usage_limit" type="number" min="1" x-model="usageLimit"
                           class="input w-full border-border bg-surface text-main text-xs font-mono @error('usage_limit') border-error @enderror"
                           placeholder="Không giới hạn">
                    <p class="mt-1 text-[11px] text-muted">Để trống nếu không giới hạn tổng số lần dùng.</p>
                    <x-forms.field-error name="usage_limit" />
                </div>
                <div>
                    <label for="coupon-per-user-limit" class="admin-label">Lượt dùng tối đa mỗi khách <span class="text-error">*</span></label>
                    <input id="coupon-per-user-limit" name="per_user_limit" type="number" min="1" x-model="perUserLimit" required
                           class="input w-full border-border bg-surface text-main text-xs font-mono @error('per_user_limit') border-error @enderror">
                    <p class="mt-1 text-[11px] text-muted">Mặc định mỗi tài khoản được dùng 1 lần.</p>
                    <x-forms.field-error name="per_user_limit" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="coupon-starts-at" class="admin-label">Thời gian bắt đầu</label>
                    <input id="coupon-starts-at" name="starts_at" type="datetime-local" x-model="startsAt"
                           class="input w-full border-border bg-surface text-main text-xs @error('starts_at') border-error @enderror">
                    <p class="mt-1 text-[11px] text-muted">Để trống nếu áp dụng ngay lập tức.</p>
                    <x-forms.field-error name="starts_at" />
                </div>
                <div>
                    <label for="coupon-expires-at" class="admin-label">Thời gian kết thúc</label>
                    <input id="coupon-expires-at" name="expires_at" type="datetime-local" x-model="expiresAt"
                           class="input w-full border-border bg-surface text-main text-xs @error('expires_at') border-error @enderror">
                    <p class="mt-1 text-[11px] text-muted">Để trống nếu không có hạn kết thúc.</p>
                    <x-forms.field-error name="expires_at" />
                </div>
            </div>

            <label class="flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                <input type="checkbox" name="is_active" value="1" x-model="isActive" class="native-checkbox text-accent focus:ring-accent rounded">
                <span>Kích hoạt mã coupon ngay</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md" type="submit">
                    {{ $coupon->exists ? 'Lưu thay đổi' : 'Tạo mã coupon' }}
                </button>
                <a href="{{ route('admin.coupons.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Hủy</a>
            </div>
        </div>

        {{-- Hộp tóm tắt điều kiện áp dụng --}}
        <aside class="admin-card space-y-4 lg:sticky lg:top-6" aria-label="Tóm tắt điều kiện áp dụng">
            <div class="flex items-center justify-between gap-2 border-b border-border pb-3">
                <h3 class="text-sm font-bold text-main">Tóm tắt điều kiện</h3>
                <span class="badge text-[11px]"
                      :class="isActive ? 'bg-success-bg text-success-text border border-success/30' : 'bg-surface-alt text-muted border border-border'"
                      x-text="isActive ? 'Đang bật' : 'Tạm tắt'"></span>
            </div>

            <dl class="space-y-3 text-xs">
                <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted">Mã áp dụng</dt>
                    <dd class="font-mono font-bold text-main" x-text="code || 'Chưa nhập'"></dd>
                </div>

                <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted shrink-0">Ưu đãi</dt>
                    <dd class="text-right font-semibold text-main">
                        <template x-if="type === 'PERCENTAGE'">
                            <span>
                                Giảm <span x-text="rawValue || '0'"></span>%<span x-show="Number(maxDiscountRaw) > 0">, tối đa <span x-text="formatNum(maxDiscountRaw)"></span> ₫</span><span x-show="!Number(maxDiscountRaw)"> (không giới hạn trần)</span>
                            </span>
                        </template>
                        <template x-if="type === 'FIXED_DISCOUNT'">
                            <span>Giảm <span x-text="formatNum(rawValue) || '0'"></span> ₫</span>
                        </template>
                        <template x-if="type === 'FREE_SHIPPING'">
                            <span>Miễn phí vận chuyển</span>
                        </template>
                    </dd>
                </div>

                <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted shrink-0">Đơn tối thiểu</dt>
                    <dd class="text-right font-mono text-main" x-text="Number(minOrderRaw) > 0 ? formatNum(minOrderRaw) + ' ₫' : '0 ₫ (Không yêu cầu)'"></dd>
                </div>

                <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted shrink-0">Tổng lượt</dt>
                    <dd class="text-right text-main" x-text="usageLimit ? usageLimit + ' lượt' : 'Không giới hạn'"></dd>
                </div>

                <div class="flex items-baseline justify-between gap-2">
                    <dt class="text-muted shrink-0">Mỗi khách</dt>
                    <dd class="text-right text-main" x-text="(perUserLimit || 1) + ' lượt / tài khoản'"></dd>
                </div>

                <div class="pt-2 border-t border-border/70 space-y-1.5">
                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-muted shrink-0">Bắt đầu</dt>
                        <dd class="text-right text-main" x-text="startsAt ? formatDateTime(startsAt) : 'Ngay khi lưu'"></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-muted shrink-0">Kết thúc</dt>
                        <dd class="text-right text-main" x-text="expiresAt ? formatDateTime(expiresAt) : 'Không thời hạn'"></dd>
                    </div>
                </div>
            </dl>
        </aside>
    </form>
</div>
@endsection
