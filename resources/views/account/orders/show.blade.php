@extends('layouts.store')

@section('title', 'Đơn '.$order->order_code)

@php
    $vnd = fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
    $tz = (string) config('store.business_timezone');
    $address = $order->shipping_address_snapshot ?? [];
    $justPlaced = session('status') !== null && $order->order_status === App\Enums\OrderStatus::Pending;
    $canReview = $order->order_status === App\Enums\OrderStatus::Delivered && $order->payment_status === App\Enums\OrderPaymentStatus::Paid;
@endphp

@section('content')
    <div class="mx-auto mt-6 max-w-4xl space-y-8 pb-20 sm:mt-10">
        @if ($justPlaced)
            <div class="text-center rounded-2xl border border-success/30 bg-success-bg/40 p-8 shadow-card">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-success text-white shadow-card">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                </div>
                <h1 class="text-3xl font-extrabold text-main sm:text-4xl">Đặt hàng thành công!</h1>
                <p class="mt-2 text-sm text-muted sm:text-base">Cảm ơn bạn. Cửa hàng sẽ liên hệ xác nhận đơn trong vòng 24 giờ.</p>
            </div>
        @else
            <div class="space-y-3">
                <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted transition-colors hover:text-accent">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Đơn hàng của tôi</span>
                </a>
                <h1 class="break-all text-2xl font-extrabold text-main sm:text-4xl tracking-tight">Đơn {{ $order->order_code }}</h1>
                @if (session('status'))
                    <p role="status" class="rounded-xl border border-success/30 bg-success-bg p-4 text-sm font-medium text-success-text shadow-subtle">{{ session('status') }}</p>
                @endif
            </div>
        @endif
        @if ($order->payment_method === App\Enums\PaymentMethod::PrepaidDemo && $order->payment_status === App\Enums\OrderPaymentStatus::Unpaid && $order->order_status !== App\Enums\OrderStatus::Cancelled)
            <a href="{{ route('orders.payment.show', $order->order_code) }}" class="block rounded-xl border border-warning/40 bg-warning-bg p-4 text-center font-semibold text-warning-text hover:bg-warning-bg/80 transition-colors shadow-subtle">Tiếp tục thanh toán mô phỏng · {{ $vnd($order->total_amount) }}</a>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-error/30 bg-error-bg p-4 text-sm font-medium text-error-text shadow-subtle">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-8">
            <h2 class="mb-5 text-xl font-bold text-main">Chi tiết đơn hàng</h2>
            <div class="grid grid-cols-2 gap-4 border-b border-border/60 pb-5 text-sm">
                <div><p class="text-xs text-muted">Mã đơn hàng</p><p class="break-all font-semibold text-main mt-0.5">{{ $order->order_code }}</p></div>
                <div><p class="text-xs text-muted">Ngày đặt</p><p class="font-semibold text-main mt-0.5">{{ $order->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</p></div>
                <div><p class="text-xs text-muted">Trạng thái</p><div class="mt-1"><x-orders.status-badge :status="$order->order_status" /></div></div>
                <div><p class="text-xs text-muted">Thanh toán</p><p class="font-semibold text-main mt-0.5">{{ $order->payment_method->label() }} · {{ $order->payment_status->label() }}</p></div>
                <div class="col-span-2 pt-2"><p class="text-xs text-muted">Tổng tiền</p><p class="text-2xl font-bold text-accent mt-0.5">{{ $vnd($order->total_amount) }}</p></div>
            </div>
            <div class="border-b border-border/60 py-5 text-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-muted">Giao đến</p>
                <p class="font-semibold text-main mt-1">{{ $order->shipping_name }} · {{ $order->shipping_phone }}</p>
                <p class="text-muted text-xs sm:text-sm mt-0.5">{{ implode(', ', array_filter([$address['street'] ?? null, $address['ward_name'] ?? null, $address['province_name'] ?? null])) }}</p>
                @if ($order->customer_note)<p class="mt-3 rounded-lg bg-surface p-3 text-xs text-muted">Ghi chú: <span class="text-main">{{ $order->customer_note }}</span></p>@endif
                @if ($order->cancel_reason)<p class="mt-3 rounded-lg border border-error/30 bg-error-bg p-3 text-xs font-medium text-error-text">Lý do hủy: {{ $order->cancel_reason }}</p>@endif
            </div>

            <div class="space-y-4 py-5">
                @foreach ($order->items as $item)
                    <div class="flex gap-4 border-b border-border/60 pb-4 last:border-b-0 last:pb-0">
                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-border bg-surface">
                            <img src="{{ $item->thumbnail_snapshot ?? asset('legacy/img/men.jpg') }}" alt="{{ $item->product_name_snapshot }}" class="h-full w-full object-cover" loading="lazy" decoding="async" width="80" height="80">
                        </div>
                        <div class="min-w-0 flex-1 break-words text-sm">
                            <p class="font-semibold text-main text-sm sm:text-base">{{ $item->product_name_snapshot }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ $item->color_name_snapshot }} · Size <span class="font-medium text-main">{{ $item->size_value_snapshot }}</span> · SL {{ $item->quantity }} × {{ $vnd($item->unit_price) }}</p>
                            <p class="mt-1.5 font-bold text-accent">{{ $vnd($item->net_amount) }}</p>
                            @if ($canReview)
                                @foreach ($item->units as $unit)
                                    @php($review = $unit->review)
                                    <details class="mt-3 rounded-xl border border-border bg-surface/40 p-3.5" @if ($errors->any() && (int) old('unit_id') === $unit->id) open @endif>
                                        <summary class="cursor-pointer text-xs font-semibold text-main hover:text-accent select-none">
                                            Đôi #{{ $unit->unit_no }} ·
                                            @if ($review) <x-reviews.stars :rating="$review->rating" class="align-middle inline-block text-warning" /> Sửa đánh giá @if (! $review->is_visible)<span class="text-xs text-muted">(đang bị ẩn)</span>@endif
                                            @else Viết đánh giá @endif
                                        </summary>
                                        <form method="POST" action="{{ route('account.reviews.store') }}" class="mt-3 space-y-3" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault(); return false; } submitting = true">
                                            @csrf
                                            <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                                            <label class="block text-xs font-bold uppercase tracking-wider text-muted">Số sao
                                                <select name="rating" class="input mt-1 block w-full text-xs">
                                                    @foreach (array_reverse(App\Http\Requests\Account\ReviewRequest::RATINGS) as $value)
                                                        <option value="{{ $value }}" @selected(old('unit_id') == $unit->id ? old('rating') === $value : ($review ? number_format((float) $review->rating, 1, '.', '') === $value : $value === '5.0'))>{{ rtrim(rtrim(str_replace('.', ',', $value), '0'), ',') }} sao</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="block text-xs font-bold uppercase tracking-wider text-muted">Nhận xét
                                                <textarea name="comment" rows="3" minlength="10" maxlength="2000" required class="input mt-1 w-full text-xs font-normal" placeholder="Giày đi có êm không, size có chuẩn không...">{{ old('unit_id') == $unit->id ? old('comment') : $review?->comment }}</textarea>
                                            </label>
                                            @if ((int) old('unit_id') === $unit->id)@foreach ($errors->all() as $error)<p class="text-xs text-error font-medium">{{ $error }}</p>@endforeach @endif
                                            @if ($review?->admin_reply)<p class="rounded-lg bg-surface p-2.5 text-xs text-muted"><span class="font-semibold text-main">Cửa hàng phản hồi:</span> {{ $review->admin_reply }}</p>@endif
                                            <button type="submit" :disabled="submitting" class="btn px-4 py-2 text-xs font-semibold disabled:opacity-50">
                                                <span x-show="!submitting">{{ $review ? 'Cập nhật đánh giá' : 'Gửi đánh giá' }}</span>
                                                <span x-cloak x-show="submitting" class="inline-flex items-center gap-1"><x-ui.spinner :compact="true" /> Đang lưu...</span>
                                            </button>
                                        </form>
                                    </details>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <dl class="space-y-2 border-t border-border/60 pt-4 text-sm">
                <div class="flex justify-between"><dt class="text-muted">Tạm tính</dt><dd class="font-medium text-main">{{ $vnd($order->subtotal_amount) }}</dd></div>
                @if ($order->merchandise_discount_amount > 0)<div class="flex justify-between"><dt class="text-muted">Coupon {{ $order->coupon_code_snapshot }}</dt><dd class="font-medium text-accent">−{{ $vnd($order->merchandise_discount_amount) }}</dd></div>@endif
                @if ($order->shipping_discount_amount > 0)<div class="flex justify-between"><dt class="text-muted">Giảm vận chuyển</dt><dd class="font-medium text-accent">−{{ $vnd($order->shipping_discount_amount) }}</dd></div>@endif
                <div class="flex justify-between"><dt class="text-muted">Phí vận chuyển</dt><dd class="font-medium text-main">{{ $order->shipping_fee === 0 ? 'Miễn phí' : $vnd($order->shipping_fee) }}</dd></div>
                <div class="flex justify-between border-t border-border/60 pt-3 text-lg font-bold text-main"><dt>Tổng cộng</dt><dd class="text-xl font-bold text-accent">{{ $vnd($order->total_amount) }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-border bg-surface-card p-6 shadow-card sm:p-8">
            <h2 class="mb-4 text-xl font-bold text-main">Lịch sử đơn hàng</h2>
            <ol class="space-y-3 text-sm">
                @foreach ($order->statusHistories as $history)
                    <li class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-3 last:border-b-0 last:pb-0">
                        <div class="flex items-center gap-2.5">
                            <x-orders.status-badge :status="$history->to_status" />
                            <span class="text-xs text-muted">{{ $history->actor_id === $order->user_id ? 'Bạn' : 'Cửa hàng' }}</span>
                        </div>
                        <span class="text-xs text-muted">{{ $history->created_at->setTimezone($tz)->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        @if ($order->order_status === App\Enums\OrderStatus::Pending)
            <section class="rounded-2xl border border-error/30 bg-surface-card p-6 shadow-card sm:p-8" x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }">
                <h2 class="text-xl font-bold text-main">Hủy đơn hàng</h2>
                <p class="mt-1 text-xs sm:text-sm text-muted">Bạn có thể tự hủy khi đơn còn chờ cửa hàng xác nhận.</p>
                <button type="button" class="mt-4 rounded-xl border border-error/40 bg-error-bg px-5 py-2 text-xs font-semibold text-error-text transition hover:bg-error-bg/80" x-show="!open" x-on:click="open = true">Hủy đơn này</button>
                <form method="POST" action="{{ route('orders.cancel', $order->order_code) }}" class="mt-4 space-y-3" x-cloak x-show="open" x-data="{ cancelling: false }" x-on:submit="if (cancelling) { $event.preventDefault(); return false; } cancelling = true">
                    @csrf
                    <input type="hidden" name="expected_status" value="{{ $order->order_status->value }}">
                    <label for="cancel-reason" class="block text-xs font-bold uppercase tracking-wider text-muted">Lý do hủy</label>
                    <textarea id="cancel-reason" name="reason" rows="2" minlength="5" maxlength="500" required class="input w-full" placeholder="Ví dụ: đặt nhầm size">{{ old('reason') }}</textarea>
                    @error('reason')<p class="text-xs font-medium text-error">{{ $message }}</p>@enderror
                    <div class="flex gap-3 pt-1">
                        <button type="submit" :disabled="cancelling" class="rounded-xl bg-error px-5 py-2 text-xs font-semibold text-white transition hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!cancelling">Xác nhận hủy</span>
                            <span x-cloak x-show="cancelling" class="inline-flex items-center gap-1.5"><x-ui.spinner :compact="true" /> Đang hủy...</span>
                        </button>
                        <button type="button" class="btn-secondary px-5 py-2 text-xs font-semibold" x-on:click="open = false">Không hủy</button>
                    </div>
                </form>
            </section>
        @endif

        <div class="flex flex-wrap justify-center gap-4">
            <a href="{{ route('orders.index') }}" class="btn-secondary px-6 py-2.5 text-sm font-semibold">Xem đơn hàng của tôi</a>
            <a href="{{ route('shoes.index') }}" class="btn px-6 py-2.5 text-sm font-semibold">Tiếp tục mua sắm</a>
        </div>
    </div>
@endsection
