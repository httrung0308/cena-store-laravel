@extends('layouts.store')

@section('title', 'Thanh toán')

@php($vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫')

@section('content')
    <div class="mx-auto mt-6 max-w-6xl pb-20 sm:mt-10">
        <h1 class="mb-8 text-3xl font-extrabold text-main tracking-tight sm:text-4xl">Thanh toán</h1>

        @if ($errors->any())
            <div role="alert" class="mb-8 rounded-2xl border border-error/30 bg-error-bg p-5 text-sm text-error-text shadow-subtle">
                <p class="font-bold">Chưa thể đặt hàng</p>
                <ul class="mt-2 list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($quote['problems'] !== [])
            <div role="alert" class="mb-8 rounded-2xl border border-warning/30 bg-warning-bg p-5 text-sm text-warning-text shadow-subtle">
                <p class="font-bold">Một số sản phẩm trong giỏ cần chỉnh lại trước khi đặt hàng:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">@foreach ($quote['problems'] as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                <button type="button" class="mt-3 font-semibold text-accent underline hover:text-accent-hover transition-colors" x-data x-on:click="$dispatch('cart-open')">Mở giỏ hàng để sửa</button>
            </div>
        @endif

        <form method="POST" action="{{ route('checkout.store') }}" class="grid grid-cols-1 gap-10 lg:grid-cols-2" x-data="checkoutPage()" x-on:submit="if (submitting) { $event.preventDefault(); return false; } submitting = true">
            @csrf
            <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
            <input type="hidden" name="fingerprint" :value="fingerprint" value="{{ $quote['fingerprint'] }}">

            <section aria-labelledby="checkout-address-title" class="space-y-5">
                <h2 id="checkout-address-title" class="text-xl sm:text-2xl font-bold text-main">Địa chỉ giao hàng</h2>
                @forelse ($addresses as $option)
                    @php($outdated = $option->dataset_version !== $currentVersion)
                    <label @class([
                        'relative block rounded-2xl border p-5 transition-colors duration-150',
                        'cursor-pointer border-accent bg-surface-card shadow-card ring-2 ring-accent/20' => $address?->id === $option->id,
                        'cursor-pointer border-border bg-surface-card hover:border-accent/40 hover:bg-surface/50' => $address?->id !== $option->id && ! $outdated,
                        'cursor-not-allowed border-border/50 bg-surface/30 opacity-60' => $outdated,
                    ]) :class="String(addressId) === @js((string) $option->id) ? 'border-accent bg-surface-card shadow-card ring-2 ring-accent/20' : 'border-border bg-surface-card hover:border-accent/40 hover:bg-surface/50'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 break-words">
                                <p class="font-semibold text-main">{{ $option->recipient_name }}</p>
                                <p class="text-sm text-muted">{{ $option->phone }}</p>
                                <p class="mt-1 text-sm text-main/80">{{ $option->street_address }}, {{ $option->ward_name_snapshot }}, {{ $option->province_name_snapshot }}</p>
                                @if ($outdated)<p class="mt-1.5 text-xs font-medium text-warning-text">Địa chỉ theo địa giới cũ, vui lòng sửa trước khi dùng.</p>@endif
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                @if ($option->is_default)<span class="badge bg-success-bg text-success-text border border-success/30 font-semibold text-xs">Mặc định</span>@endif
                                <input type="radio" name="address_id" value="{{ $option->id }}" class="native-checkbox text-accent focus:ring-accent" @checked($address?->id === $option->id) @disabled($outdated)
                                    x-model="addressId" x-on:change="fetchQuote(null, $event.target.value)">
                            </div>
                        </div>
                    </label>
                @empty
                    <div class="rounded-2xl border-2 border-dashed border-border bg-surface/40 p-8 text-center">
                        <p class="text-sm text-muted">Bạn chưa có địa chỉ giao hàng.</p>
                    </div>
                @endforelse
                <a href="{{ route('account.addresses.create', ['return_to' => 'checkout']) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">+ Thêm địa chỉ mới</a>
                @error('address_id')<p role="alert" class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>@enderror

                <div class="pt-2">
                    <label for="checkout-note" class="mb-2 block text-sm font-semibold text-main">Ghi chú cho cửa hàng (không bắt buộc)</label>
                    <textarea id="checkout-note" name="customer_note" rows="3" maxlength="1000" class="input w-full" placeholder="Ví dụ: giao giờ hành chính">{{ old('customer_note') }}</textarea>
                    @error('customer_note')<p role="alert" class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-3 pt-2">
                    <p class="text-sm font-semibold text-main">Phương thức thanh toán</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 rounded-xl border border-border bg-surface-card p-4 cursor-pointer hover:border-accent/40 transition-colors" :class="paymentMethod === 'COD' && 'border-accent ring-1 ring-accent/30'">
                            <input type="radio" name="payment_method" value="COD" class="native-checkbox text-accent focus:ring-accent" @checked($paymentMethod === App\Enums\PaymentMethod::Cod) x-model="paymentMethod" x-on:change="fetchQuote('COD', null)">
                            <span class="text-sm font-medium text-main">Thanh toán khi nhận hàng (COD)</span>
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-border bg-surface-card p-4 cursor-pointer hover:border-accent/40 transition-colors" :class="paymentMethod === 'PREPAID_DEMO' && 'border-accent ring-1 ring-accent/30'">
                            <input type="radio" name="payment_method" value="PREPAID_DEMO" class="native-checkbox text-accent focus:ring-accent" @checked($paymentMethod === App\Enums\PaymentMethod::PrepaidDemo) x-model="paymentMethod" x-on:change="fetchQuote('PREPAID_DEMO', null)">
                            <span class="text-sm font-medium text-main">Trả trước (mô phỏng)</span>
                        </label>
                    </div>
                    @error('payment_method')<p role="alert" class="text-xs font-semibold text-error">{{ $message }}</p>@enderror
                </div>
            </section>

            <section aria-labelledby="checkout-summary-title" class="h-fit rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-8 relative" :class="loadingQuote && 'opacity-70 pointer-events-none'">
                <h2 id="checkout-summary-title" class="mb-5 text-xl sm:text-2xl font-bold text-main">Đơn hàng của bạn</h2>
                <div class="mb-5 max-h-96 space-y-4 overflow-y-auto pr-1">
                    @foreach ($quote['lines'] as $line)
                        <div class="flex gap-4 border-b border-border/60 pb-4">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-border bg-surface">
                                <img src="{{ $line['thumbnail_url'] ?? asset('legacy/img/men.jpg') }}" alt="{{ $line['product_name'] }}" class="h-full w-full object-cover" loading="lazy" decoding="async" width="64" height="64">
                            </div>
                            <div class="min-w-0 flex-1 break-words">
                                <p class="font-semibold text-main text-sm">{{ $line['product_name'] }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $line['color_name'] }} · Size <span class="font-medium text-main">{{ $line['size_value'] }}</span> · SL {{ $line['quantity'] }}</p>
                                <p class="mt-2 text-sm font-bold text-accent">{{ $vnd($line['line_total']) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <dl class="space-y-3 border-t border-border/60 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Tạm tính</dt><dd class="font-medium text-main" x-text="subtotalFormatted">{{ $vnd($quote['subtotal']) }}</dd></div>
                    <div class="flex justify-between text-success-text" x-show="merchandiseDiscount > 0" @if ($quote['merchandise_discount'] <= 0) style="display: none;" @endif><dt>Giảm coupon</dt><dd class="font-bold" x-text="merchandiseDiscountFormatted">−{{ $vnd($quote['merchandise_discount']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Phí vận chuyển</dt><dd class="font-medium text-main" x-text="shippingFeeFormatted">@if (! $address)—@elseif ($quote['shipping_fee'] === 0)Miễn phí @else{{ $vnd($quote['shipping_fee']) }}@endif</dd></div>
                    <div class="flex justify-between text-success-text" x-show="shippingDiscount > 0" @if ($quote['shipping_discount'] <= 0) style="display: none;" @endif><dt>Giảm phí vận chuyển</dt><dd class="font-bold" x-text="shippingDiscountFormatted">−{{ $vnd($quote['shipping_discount']) }}</dd></div>
                    <div class="flex justify-between border-t border-border/60 pt-3 text-lg font-bold text-main"><dt>Tổng cộng</dt><dd class="text-2xl font-bold text-accent" x-text="totalFormatted">{{ $vnd($quote['total']) }}</dd></div>
                </dl>
                <p class="mt-3 text-xs leading-relaxed text-muted">Miễn phí vận chuyển cho đơn từ {{ $vnd((int) config('store.shipping.free_threshold')) }}; TP. Hồ Chí Minh {{ $vnd((int) config('store.shipping.hcm_fee')) }}, tỉnh khác {{ $vnd((int) config('store.shipping.other_fee')) }}.</p>
                <div class="mt-5 rounded-xl border border-border bg-surface/50 p-4 text-sm">
                    <label for="checkout-coupon" class="block text-xs font-bold uppercase tracking-wider text-muted">Mã giảm giá</label>
                    <div class="mt-2 flex gap-2">
                        <input id="checkout-coupon" name="coupon_code" value="{{ old('coupon_code', $couponCode) }}" class="input min-w-0 flex-1 text-xs" maxlength="50" placeholder="Nhập mã coupon" x-on:keydown.enter.prevent="fetchQuote()">
                        <button type="button" x-on:click="fetchQuote()" :disabled="loadingQuote" class="btn px-4 py-2 text-xs font-semibold disabled:opacity-50">
                            <span x-show="!loadingQuote">Áp dụng</span>
                            <span x-cloak x-show="loadingQuote" class="inline-flex items-center gap-1"><x-ui.spinner :compact="true" /></span>
                        </button>
                    </div>
                    <div class="mt-2 text-xs font-medium">
                        <p role="alert" class="text-error" x-show="couponError" x-text="couponError">@if ($quote['coupon_error']){{ $quote['coupon_error'] }}@endif</p>
                        <p class="text-success-text" x-show="!couponError && appliedCoupon" x-text="'Đã áp dụng mã ' + appliedCoupon + '.'">@if (! $quote['coupon_error'] && $quote['coupon_code'])Đã áp dụng mã {{ $quote['coupon_code'] }}.@endif</p>
                    </div>
                </div>
                <button type="submit" class="btn mt-6 w-full py-3.5 text-base shadow-card font-semibold disabled:cursor-not-allowed disabled:opacity-40"
                    @disabled($quote['fingerprint'] === null || $quote['problems'] !== []) x-bind:disabled="submitting || loadingQuote || fingerprint === null || problems.length > 0">
                    <span x-show="!submitting">Đặt hàng</span>
                    <span x-cloak x-show="submitting" class="inline-flex items-center gap-2">
                        <x-ui.spinner :compact="true" />
                        <span>Đang xử lý đặt hàng...</span>
                    </span>
                </button>
                @if (! $address)<p class="mt-3 text-sm font-medium text-error" x-show="!addressId">Vui lòng thêm hoặc chọn địa chỉ giao hàng.</p>@endif
            </section>
        </form>
    </div>
    <script>
        function checkoutPage() {
            return {
                submitting: false,
                loadingQuote: false,
                addressId: @js((string) ($address?->id ?? '')),
                paymentMethod: @js($paymentMethod->value),
                couponCode: @js($couponCode ?? ''),
                fingerprint: @js($quote['fingerprint']),
                subtotalFormatted: @js($vnd($quote['subtotal'])),
                merchandiseDiscount: @js($quote['merchandise_discount']),
                merchandiseDiscountFormatted: @js('−'.$vnd($quote['merchandise_discount'])),
                shippingFee: @js($quote['shipping_fee']),
                shippingFeeFormatted: @js(! $address ? '—' : ($quote['shipping_fee'] === 0 ? 'Miễn phí' : $vnd($quote['shipping_fee']))),
                shippingDiscount: @js($quote['shipping_discount']),
                shippingDiscountFormatted: @js('−'.$vnd($quote['shipping_discount'])),
                totalFormatted: @js($vnd($quote['total'])),
                couponError: @js($quote['coupon_error']),
                appliedCoupon: @js($quote['coupon_code']),
                problems: @js($quote['problems']),

                async fetchQuote(method = null, address = null) {
                    if (method) this.paymentMethod = method;
                    if (address) this.addressId = address;
                    const inputVal = document.getElementById('checkout-coupon')?.value?.trim() ?? '';
                    this.couponCode = inputVal;
                    this.loadingQuote = true;
                    try {
                        const params = new URLSearchParams({
                            address: this.addressId,
                            payment_method: this.paymentMethod,
                            coupon_code: this.couponCode,
                        });
                        const res = await fetch(@js(route('checkout.show')) + '?' + params.toString(), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            }
                        });
                        if (!res.ok) {
                            window.location = @js(route('checkout.show')) + '?' + params.toString();
                            return;
                        }
                        const data = await res.json();
                        if (data.empty && data.redirect) {
                            window.location = data.redirect;
                            return;
                        }
                        const q = data.quote;
                        this.fingerprint = q.fingerprint;
                        this.subtotalFormatted = data.formatted.subtotal;
                        this.merchandiseDiscount = q.merchandise_discount;
                        this.merchandiseDiscountFormatted = '−' + data.formatted.merchandise_discount;
                        this.shippingFee = q.shipping_fee;
                        this.shippingFeeFormatted = this.addressId ? data.formatted.shipping_fee : '—';
                        this.shippingDiscount = q.shipping_discount;
                        this.shippingDiscountFormatted = '−' + data.formatted.shipping_discount;
                        this.totalFormatted = data.formatted.total;
                        this.couponError = q.coupon_error;
                        this.appliedCoupon = q.coupon_code;
                        this.problems = q.problems || [];
                    } catch (e) {
                        const params = new URLSearchParams({
                            address: this.addressId,
                            payment_method: this.paymentMethod,
                            coupon_code: this.couponCode,
                        });
                        window.location = @js(route('checkout.show')) + '?' + params.toString();
                    } finally {
                        this.loadingQuote = false;
                    }
                }
            };
        }
    </script>
@endsection
