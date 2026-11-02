@extends('layouts.store')

@section('title', 'Thanh toán')

@php($vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫')

@section('content')
    <div class="mx-auto mt-10 max-w-6xl pb-20">
        <h1 class="mb-8 text-3xl font-bold">Thanh toán</h1>

        @if ($errors->any())
            <div role="alert" class="mb-8 rounded-3xl border border-red-200 bg-red-50 p-4 text-sm text-red-600">
                <p class="font-semibold">Chưa thể đặt hàng</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($quote['problems'] !== [])
            <div role="alert" class="mb-8 rounded-3xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Một số sản phẩm trong giỏ cần chỉnh lại trước khi đặt hàng:</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($quote['problems'] as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                <button type="button" class="mt-3 font-semibold underline" x-data x-on:click="$dispatch('cart-open')">Mở giỏ hàng để sửa</button>
            </div>
        @endif

        <form method="POST" action="{{ route('checkout.store') }}" class="grid grid-cols-1 gap-10 lg:grid-cols-2" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
            <input type="hidden" name="fingerprint" value="{{ $quote['fingerprint'] }}">
            <input type="hidden" name="payment_method" value="COD">

            <section aria-labelledby="checkout-address-title" class="space-y-4">
                <h2 id="checkout-address-title" class="text-2xl font-semibold">Địa chỉ giao hàng</h2>
                @forelse ($addresses as $option)
                    @php($outdated = $option->dataset_version !== $currentVersion)
                    <label @class([
                        'block rounded-lg border-2 p-4 transition-colors',
                        'cursor-pointer border-black bg-zinc-50' => $address?->id === $option->id,
                        'cursor-pointer border-zinc-200 hover:border-zinc-400' => $address?->id !== $option->id && ! $outdated,
                        'cursor-not-allowed border-zinc-100 opacity-60' => $outdated,
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 break-words">
                                <p class="font-semibold">{{ $option->recipient_name }}</p>
                                <p class="text-sm text-zinc-600">{{ $option->phone }}</p>
                                <p class="mt-1 text-sm">{{ $option->street_address }}, {{ $option->ward_name_snapshot }}, {{ $option->province_name_snapshot }}</p>
                                @if ($outdated)<p class="mt-1 text-xs text-amber-700">Địa chỉ theo địa giới cũ, vui lòng sửa trước khi dùng.</p>@endif
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                @if ($option->is_default)<span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-600">Mặc định</span>@endif
                                <input type="radio" name="address_id" value="{{ $option->id }}" class="native-checkbox accent-black" @checked($address?->id === $option->id) @disabled($outdated)
                                    x-on:change="window.location = @js(route('checkout.show')) + '?address=' + $event.target.value">
                            </div>
                        </div>
                    </label>
                @empty
                    <p class="rounded-lg border-2 border-dashed border-zinc-300 p-6 text-sm text-zinc-600">Bạn chưa có địa chỉ giao hàng.</p>
                @endforelse
                <a href="{{ route('account.addresses.create', ['return_to' => 'checkout']) }}" class="inline-block text-sm text-blue-600 hover:underline">+ Thêm địa chỉ mới</a>

                <div>
                    <label for="checkout-note" class="mb-2 block font-semibold">Ghi chú cho cửa hàng (không bắt buộc)</label>
                    <textarea id="checkout-note" name="customer_note" rows="3" maxlength="1000" class="input w-full border-zinc-300" placeholder="Ví dụ: giao giờ hành chính">{{ old('customer_note') }}</textarea>
                </div>
            </section>

            <section aria-labelledby="checkout-summary-title" class="h-fit rounded-lg border p-6">
                <h2 id="checkout-summary-title" class="mb-4 text-2xl font-semibold">Đơn hàng của bạn</h2>
                <div class="mb-4 max-h-96 space-y-3 overflow-y-auto">
                    @foreach ($quote['lines'] as $line)
                        <div class="flex gap-3 border-b pb-3">
                            <img src="{{ $line['thumbnail_url'] ?? asset('legacy/img/men.jpg') }}" alt="{{ $line['product_name'] }}" class="h-20 w-20 rounded object-cover">
                            <div class="min-w-0 flex-1 break-words">
                                <p class="font-semibold">{{ $line['product_name'] }}</p>
                                <p class="text-sm text-zinc-600">{{ $line['color_name'] }} · Size {{ $line['size_value'] }} · SL {{ $line['quantity'] }}</p>
                                <p class="mt-1 font-semibold">{{ $vnd($line['line_total']) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <dl class="space-y-2 border-t pt-4 text-sm">
                    <div class="flex justify-between"><dt>Tạm tính</dt><dd>{{ $vnd($quote['subtotal']) }}</dd></div>
                    <div class="flex justify-between"><dt>Phí vận chuyển</dt><dd>@if (! $address)—@elseif ($quote['shipping_fee'] === 0)Miễn phí @else{{ $vnd($quote['shipping_fee']) }}@endif</dd></div>
                    <div class="flex justify-between border-t pt-2 text-lg font-semibold"><dt>Tổng cộng</dt><dd>{{ $vnd($quote['total']) }}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-zinc-500">Miễn phí vận chuyển cho đơn từ {{ $vnd((int) config('store.shipping.free_threshold')) }}; TP. Hồ Chí Minh {{ $vnd((int) config('store.shipping.hcm_fee')) }}, tỉnh khác {{ $vnd((int) config('store.shipping.other_fee')) }}.</p>
                <div class="mt-4 rounded border border-black px-4 py-3 text-sm">
                    <p class="font-semibold">Phương thức thanh toán</p>
                    <p class="mt-1 text-zinc-700">Thanh toán khi nhận hàng (COD)</p>
                </div>
                <button type="submit" class="btn mt-6 w-full py-3 text-lg disabled:cursor-not-allowed disabled:opacity-50"
                    @disabled($quote['fingerprint'] === null || $quote['problems'] !== []) x-bind:disabled="submitting || {{ $quote['fingerprint'] === null || $quote['problems'] !== [] ? 'true' : 'false' }}">
                    <span x-text="submitting ? 'Đang xử lý...' : 'Đặt hàng'">Đặt hàng</span>
                </button>
                @if (! $address)<p class="mt-2 text-sm text-red-500">Vui lòng thêm hoặc chọn địa chỉ giao hàng.</p>@endif
            </section>
        </form>
    </div>
@endsection
