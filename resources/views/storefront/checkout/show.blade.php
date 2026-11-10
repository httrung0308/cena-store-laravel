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

        <form method="POST" action="{{ route('checkout.store') }}" class="grid grid-cols-1 gap-10 lg:grid-cols-2" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
            <input type="hidden" name="fingerprint" value="{{ $quote['fingerprint'] }}">

            <section aria-labelledby="checkout-address-title" class="space-y-5">
                <h2 id="checkout-address-title" class="text-xl sm:text-2xl font-bold text-main">Địa chỉ giao hàng</h2>
                @forelse ($addresses as $option)
                    @php($outdated = $option->dataset_version !== $currentVersion)
                    <label @class([
                        'relative block rounded-2xl border p-5 transition-colors duration-150',
                        'cursor-pointer border-accent bg-surface-card shadow-card ring-2 ring-accent/20' => $address?->id === $option->id,
                        'cursor-pointer border-border bg-surface-card hover:border-accent/40 hover:bg-surface/50' => $address?->id !== $option->id && ! $outdated,
                        'cursor-not-allowed border-border/50 bg-surface/30 opacity-60' => $outdated,
                    ])>
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
                                    x-on:change="const params = new URLSearchParams({ address: $event.target.value, payment_method: document.querySelector('input[name=payment_method]:checked')?.value ?? 'COD', coupon_code: document.getElementById('checkout-coupon')?.value ?? '' }); window.location = @js(route('checkout.show')) + '?' + params.toString()">
                            </div>
                        </div>
                    </label>
                @empty
                    <div class="rounded-2xl border-2 border-dashed border-border bg-surface/40 p-8 text-center">
                        <p class="text-sm text-muted">Bạn chưa có địa chỉ giao hàng.</p>
                    </div>
                @endforelse
                <a href="{{ route('account.addresses.create', ['return_to' => 'checkout']) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-accent hover:text-accent-hover hover:underline transition-colors">+ Thêm địa chỉ mới</a>

                <div class="pt-2">
                    <label for="checkout-note" class="mb-2 block text-sm font-semibold text-main">Ghi chú cho cửa hàng (không bắt buộc)</label>
                    <textarea id="checkout-note" name="customer_note" rows="3" maxlength="1000" class="input w-full" placeholder="Ví dụ: giao giờ hành chính">{{ old('customer_note') }}</textarea>
                </div>
                <div class="space-y-3 pt-2">
                    <p class="text-sm font-semibold text-main">Phương thức thanh toán</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 rounded-xl border border-border bg-surface-card p-4 cursor-pointer hover:border-accent/40 transition-colors">
                            <input type="radio" name="payment_method" value="COD" class="native-checkbox text-accent focus:ring-accent" @checked($paymentMethod === App\Enums\PaymentMethod::Cod) x-on:change="updateCheckoutQuote($event.target.value)">
                            <span class="text-sm font-medium text-main">Thanh toán khi nhận hàng (COD)</span>
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-border bg-surface-card p-4 cursor-pointer hover:border-accent/40 transition-colors">
                            <input type="radio" name="payment_method" value="PREPAID_DEMO" class="native-checkbox text-accent focus:ring-accent" @checked($paymentMethod === App\Enums\PaymentMethod::PrepaidDemo) x-on:change="updateCheckoutQuote($event.target.value)">
                            <span class="text-sm font-medium text-main">Trả trước (mô phỏng)</span>
                        </label>
                    </div>
                </div>
            </section>

            <section aria-labelledby="checkout-summary-title" class="h-fit rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-8">
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
                    <div class="flex justify-between"><dt class="text-muted">Tạm tính</dt><dd class="font-medium text-main">{{ $vnd($quote['subtotal']) }}</dd></div>
                    @if ($quote['merchandise_discount'] > 0)<div class="flex justify-between text-success-text"><dt>Giảm coupon</dt><dd class="font-bold">−{{ $vnd($quote['merchandise_discount']) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-muted">Phí vận chuyển</dt><dd class="font-medium text-main">@if (! $address)—@elseif ($quote['shipping_fee'] === 0)Miễn phí @else{{ $vnd($quote['shipping_fee']) }}@endif</dd></div>
                    @if ($quote['shipping_discount'] > 0)<div class="flex justify-between text-success-text"><dt>Giảm phí vận chuyển</dt><dd class="font-bold">−{{ $vnd($quote['shipping_discount']) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-border/60 pt-3 text-lg font-bold text-main"><dt>Tổng cộng</dt><dd class="text-2xl font-bold text-accent">{{ $vnd($quote['total']) }}</dd></div>
                </dl>
                <p class="mt-3 text-xs leading-relaxed text-muted">Miễn phí vận chuyển cho đơn từ {{ $vnd((int) config('store.shipping.free_threshold')) }}; TP. Hồ Chí Minh {{ $vnd((int) config('store.shipping.hcm_fee')) }}, tỉnh khác {{ $vnd((int) config('store.shipping.other_fee')) }}.</p>
                <div class="mt-5 rounded-xl border border-border bg-surface/50 p-4 text-sm">
                    <label for="checkout-coupon" class="block text-xs font-bold uppercase tracking-wider text-muted">Mã giảm giá</label>
                    <div class="mt-2 flex gap-2">
                        <input id="checkout-coupon" name="coupon_code" value="{{ old('coupon_code', $couponCode) }}" class="input min-w-0 flex-1 text-xs" maxlength="50" placeholder="Nhập mã coupon" x-on:keydown.enter.prevent="updateCheckoutQuote(document.querySelector('input[name=payment_method]:checked')?.value ?? 'COD')">
                        <button type="button" x-on:click="updateCheckoutQuote(document.querySelector('input[name=payment_method]:checked')?.value ?? 'COD')" class="btn px-4 py-2 text-xs font-semibold">Áp dụng</button>
                    </div>
                    @if ($quote['coupon_error'])
                        <p role="alert" class="mt-2 text-xs font-medium text-error">{{ $quote['coupon_error'] }}</p>
                    @elseif ($quote['coupon_code'])
                        <p class="mt-2 text-xs font-medium text-success-text">Đã áp dụng mã {{ $quote['coupon_code'] }}.</p>
                    @endif
                </div>
                <button type="submit" class="btn mt-6 w-full py-3.5 text-base shadow-card font-semibold disabled:cursor-not-allowed disabled:opacity-40"
                    @disabled($quote['fingerprint'] === null || $quote['problems'] !== []) x-bind:disabled="submitting || {{ $quote['fingerprint'] === null || $quote['problems'] !== [] ? 'true' : 'false' }}">
                    <span x-text="submitting ? 'Đang xử lý...' : 'Đặt hàng'">Đặt hàng</span>
                </button>
                @if (! $address)<p class="mt-3 text-sm font-medium text-error">Vui lòng thêm hoặc chọn địa chỉ giao hàng.</p>@endif
            </section>
        </form>
    </div>
    <script>
        function updateCheckoutQuote(method) {
            const params = new URLSearchParams({
                address: @js((string) ($address?->id ?? '')),
                payment_method: method,
                coupon_code: document.getElementById('checkout-coupon')?.value ?? '',
            });
            window.location = @js(route('checkout.show')) + '?' + params.toString();
        }
    </script>
@endsection
