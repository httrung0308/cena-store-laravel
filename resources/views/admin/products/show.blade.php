@extends('layouts.admin')
@section('title', 'Chi tiết sản phẩm')
@section('eyebrow', 'QUẢN TRỊ KHO HÀNG')
@section('actions')
    <a class="rounded-xl border border-border bg-surface px-4 py-2 text-xs font-bold text-muted transition-colors hover:bg-border/40 hover:text-main" href="{{ route('admin.products.index') }}">← Danh sách sản phẩm</a>
    @unless ($product->trashed())
        <a class="rounded-xl bg-accent px-4 py-2 text-xs font-bold text-accent-text transition-colors hover:bg-accent-hover" href="{{ route('admin.products.edit', $product) }}">Sửa thông tin</a>
    @endunless
@endsection

@section('content')
    <section class="admin-card mb-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-extrabold text-main">{{ $product->name }}</h2>
                <p class="mt-1 font-mono text-xs text-muted">{{ $product->slug }} <span aria-hidden="true">·</span> {{ $product->historicalCategory?->name ?? 'Chưa phân loại' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                @if ($product->trashed())
                    <span class="rounded-full border border-error/30 bg-error-bg px-3 py-1 text-error">Đã xóa mềm</span>
                @elseif ($product->is_active)
                    <span class="rounded-full border border-success/30 bg-success-bg px-3 py-1 text-success">Đang bán</span>
                @else
                    <span class="rounded-full bg-muted/15 px-3 py-1 text-muted">Đã ngừng bán</span>
                @endif
            </div>
        </div>
        <p class="text-xs leading-5 text-muted">Chọn một SKU để tra cứu lịch sử. Đây là màn hình chỉ đọc; số dư khả dụng được tính bằng tồn kho trừ số lượng đang giữ.</p>
    </section>

    <section class="mb-8 space-y-4" aria-labelledby="variant-list-title">
        <div>
            <h2 id="variant-list-title" class="text-base font-extrabold text-main">SKU và số lượng hiện tại</h2>
            <p class="mt-1 text-xs text-muted">Tồn kho, đang giữ và cách ly là ba số dư riêng.</p>
        </div>

        @forelse ($colors as $color)
            <div class="overflow-hidden rounded-2xl border border-border bg-surface-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-surface px-4 py-3">
                    <h3 class="text-sm font-bold text-main">{{ $color->color_name }} <span class="ml-1 font-mono text-xs font-medium text-muted">{{ $color->style_color_code }}</span></h3>
                    @if ($color->trashed())
                        <span class="rounded-full bg-muted/15 px-2.5 py-1 text-[10px] font-bold text-muted">Màu đã xóa mềm</span>
                    @elseif (! $color->is_active)
                        <span class="rounded-full bg-muted/15 px-2.5 py-1 text-[10px] font-bold text-muted">Màu đã ngừng bán</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-left text-xs text-muted">
                        <thead class="border-b border-border bg-surface/50 text-[10px] font-bold uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3">SKU / size</th>
                                <th scope="col" class="px-4 py-3 text-right">Tồn kho</th>
                                <th scope="col" class="px-4 py-3 text-right">Đang giữ</th>
                                <th scope="col" class="px-4 py-3 text-right">Khả dụng</th>
                                <th scope="col" class="px-4 py-3 text-right">Cách ly</th>
                                <th scope="col" class="px-4 py-3 text-right">Giá vốn bình quân</th>
                                <th scope="col" class="px-4 py-3 text-right">Lịch sử</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                        @forelse ($color->variants as $variant)
                            @php($availableQuantity = $variant->stock_qty - $variant->reserved_qty)
                            <tr @class(['bg-accent/5' => $selectedVariant?->id === $variant->id])>
                                <td class="px-4 py-3">
                                    <span class="block font-mono font-bold text-main">{{ $variant->sku }}</span>
                                    <span class="mt-1 inline-flex flex-wrap items-center gap-1.5 text-[10px]">
                                        <span>Size {{ $variant->size_value }}</span>
                                        @if ($variant->trashed())
                                            <span class="rounded-full bg-muted/15 px-2 py-0.5 font-bold text-muted">Đã xóa mềm</span>
                                        @elseif (! $variant->is_active)
                                            <span class="rounded-full bg-muted/15 px-2 py-0.5 font-bold text-muted">Ngừng bán</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-main">{{ $variant->stock_qty }}</td>
                                <td class="px-4 py-3 text-right font-mono text-main">{{ $variant->reserved_qty }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-main">{{ $availableQuantity }}</td>
                                <td class="px-4 py-3 text-right font-mono text-main">{{ $variant->quarantine_qty }}</td>
                                <td class="px-4 py-3 text-right font-mono text-main">{{ $variant->average_cost }} ₫</td>
                                <td class="px-4 py-3 text-right">
                                    <a @class([
                                        'inline-flex rounded-lg border px-3 py-2 font-bold transition-colors focus:outline-none focus:ring-2 focus:ring-accent',
                                        'border-accent bg-accent text-accent-text' => $selectedVariant?->id === $variant->id,
                                        'border-border bg-surface-card text-accent hover:bg-surface' => $selectedVariant?->id !== $variant->id,
                                    ]) href="{{ route('admin.products.show', $product) }}?variant_id={{ $variant->id }}" aria-label="Xem thẻ kho SKU {{ $variant->sku }}">Xem thẻ kho</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-xs text-muted">Màu này chưa có SKU.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-border bg-surface-card p-8 text-center text-xs text-muted">Sản phẩm chưa có màu hoặc SKU.</div>
        @endforelse
    </section>

    @if ($selectedVariant)
        <section class="space-y-5" aria-labelledby="inventory-history-title">
            <div>
                <h2 id="inventory-history-title" class="text-base font-extrabold text-main">Thẻ kho — {{ $selectedVariant->sku }}</h2>
                <p class="mt-1 text-xs text-muted">Biến động được sắp xếp mới nhất trước. Giá vốn hiển thị trong phạm vi quyền F1 của Admin và Staff.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                @if ($reconciliation['history_incomplete'])
                    <div role="status" class="rounded-2xl border border-warning/40 bg-warning-bg p-4 text-xs leading-5 text-warning-text">
                        <strong class="block font-mono">HISTORY_INCOMPLETE</strong>
                        Không đủ lịch sử để kết luận: thiếu số dư đầu kỳ, có khoảng nối không liên tục, hoặc chưa có biến động để đối chiếu (kể cả khi các bucket hiện bằng 0). Cảnh báo này tự nó không khẳng định số dư hiện tại sai.
                    </div>
                @endif
                @if ($reconciliation['observed_balance_diff'])
                    <div role="status" class="rounded-2xl border border-error/30 bg-error-bg p-4 text-xs leading-5 text-error-text">
                        <strong class="block font-mono">OBSERVED_BALANCE_DIFF</strong>
                        Số dư sau biến động cuối khác số dư bucket hiện tại của SKU. Không có điều chỉnh tự động nào được thực hiện.
                    </div>
                @endif
                @unless ($reconciliation['history_incomplete'] || $reconciliation['observed_balance_diff'])
                    <div role="status" class="rounded-2xl border border-success/30 bg-success-bg p-4 text-xs leading-5 text-success sm:col-span-2">
                        Lịch sử liên tục từ số dư đầu tiên quan sát được và số dư sau biến động mới nhất khớp với SKU hiện tại.
                    </div>
                @endunless
            </div>

            <form method="GET" action="{{ route('admin.products.show', $product) }}" class="grid gap-3 rounded-2xl border border-border bg-surface-card p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-6" aria-label="Lọc thẻ kho">
                <div class="space-y-1">
                    <label for="movement-variant" class="text-[11px] font-bold text-main">SKU</label>
                    <select id="movement-variant" name="variant_id" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                        @foreach ($colors as $color)
                            <optgroup label="{{ $color->color_name }}">
                                @foreach ($color->variants as $variant)
                                    <option value="{{ $variant->id }}" @selected($selectedVariant->id === $variant->id)>{{ $variant->sku }} · size {{ $variant->size_value }}{{ $variant->trashed() || ! $variant->is_active ? ' · ngừng bán' : '' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="movement-from" class="text-[11px] font-bold text-main">Từ ngày</label>
                    <input id="movement-from" type="date" name="from" value="{{ request('from') }}" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                </div>
                <div class="space-y-1">
                    <label for="movement-to" class="text-[11px] font-bold text-main">Đến ngày</label>
                    <input id="movement-to" type="date" name="to" value="{{ request('to') }}" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                </div>
                <div class="space-y-1">
                    <label for="movement-action" class="text-[11px] font-bold text-main">Loại sự kiện</label>
                    <select id="movement-action" name="action" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                        <option value="">Tất cả loại</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action->value }}" @selected(request('action') === $action->value)>{{ $action->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="movement-reference-type" class="text-[11px] font-bold text-main">Loại chứng từ</label>
                    <select id="movement-reference-type" name="reference_type" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                        <option value="">Tất cả chứng từ</option>
                        @foreach ($referenceTypes as $referenceType)
                            <option value="{{ $referenceType->value }}" @selected(request('reference_type') === $referenceType->value)>{{ $referenceType->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="movement-reference-id" class="text-[11px] font-bold text-main">Mã chứng từ</label>
                    <input id="movement-reference-id" type="number" min="1" name="reference_id" value="{{ request('reference_id') }}" class="min-h-10 w-full rounded-xl border border-border bg-bg px-3 text-xs text-main focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20">
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-6">
                    <button type="submit" class="min-h-10 flex-1 rounded-xl bg-accent px-4 text-xs font-bold text-accent-text transition-colors hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2">Lọc thẻ kho</button>
                    <a href="{{ route('admin.products.show', ['product' => $product, 'variant_id' => $selectedVariant->id]) }}" class="inline-flex min-h-10 items-center rounded-xl border border-border px-3 text-xs font-bold text-muted hover:bg-surface">Xóa lọc</a>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-border bg-surface-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1280px] border-collapse text-left text-xs text-muted">
                        <caption class="sr-only">Lịch sử biến động ba bucket tồn kho của SKU {{ $selectedVariant->sku }}</caption>
                        <thead class="bg-surface text-[10px] font-bold uppercase tracking-wide text-main">
                            <tr class="border-b border-border">
                                <th rowspan="2" scope="col" class="px-3 py-3">Movement / ngày giờ</th>
                                <th rowspan="2" scope="col" class="px-3 py-3">Sự kiện / chứng từ</th>
                                <th colspan="3" scope="colgroup" class="px-3 py-2 text-center">Tồn kho</th>
                                <th colspan="3" scope="colgroup" class="px-3 py-2 text-center">Đang giữ</th>
                                <th colspan="3" scope="colgroup" class="px-3 py-2 text-center">Cách ly</th>
                                <th rowspan="2" scope="col" class="px-3 py-3">Người thực hiện</th>
                                <th rowspan="2" scope="col" class="px-3 py-3 text-right">Giá vốn liên quan</th>
                            </tr>
                            <tr class="border-b border-border bg-surface/60">
                                @foreach (['Trước', 'Thay đổi', 'Sau', 'Trước', 'Thay đổi', 'Sau', 'Trước', 'Thay đổi', 'Sau'] as $label)
                                    <th scope="col" class="px-3 py-2 text-right">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                        @forelse ($movements as $movement)
                            <tr class="align-top hover:bg-surface/50">
                                <td class="px-3 py-3">
                                    <strong class="block font-mono text-main">#{{ $movement->id }}</strong>
                                    <time class="mt-1 block whitespace-nowrap text-[10px]" datetime="{{ $movement->created_at?->toISOString() }}">{{ $movement->created_at?->setTimezone(config('store.business_timezone'))->format('d/m/Y H:i:s') }}</time>
                                </td>
                                <td class="max-w-48 px-3 py-3">
                                    <span class="block font-bold text-main">{{ $movement->action?->label() ?? $movement->action }}</span>
                                    <span class="mt-1 block text-[10px]">{{ $movement->reference_type?->label() ?? $movement->reference_type?->value ?? 'Chứng từ' }} #{{ $movement->reference_id ?? '—' }}</span>
                                </td>
                                @foreach ([['stock_before', 'stock_delta', 'stock_after'], ['reserved_before', 'reserved_delta', 'reserved_after'], ['quarantine_before', 'quarantine_delta', 'quarantine_after']] as $fields)
                                    @foreach ($fields as $field)
                                        <td class="px-3 py-3 text-right font-mono text-main">{{ $movement->{$field} > 0 && str_ends_with($field, '_delta') ? '+' : '' }}{{ $movement->{$field} }}</td>
                                    @endforeach
                                @endforeach
                                <td class="px-3 py-3 text-main">{{ $movement->actor_name_snapshot ?? $movement->actor?->full_name ?? 'Hệ thống' }}</td>
                                <td class="px-3 py-3 text-right font-mono text-main">
                                    @if ($movement->unit_cost_basis !== null)
                                        <span class="block">Đơn vị: {{ $movement->unit_cost_basis }} ₫</span>
                                    @endif
                                    <span class="block text-[10px]">Bình quân: {{ $movement->average_cost_before }} → {{ $movement->average_cost_after }} ₫</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="px-4 py-10 text-center text-xs text-muted">Không có biến động phù hợp với bộ lọc.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <x-ui.pagination :paginator="$movements" />
            </div>
        </section>
    @else
        <div class="rounded-2xl border border-dashed border-border bg-surface-card p-8 text-center text-xs text-muted">
            Chọn SKU ở trên để xem lịch sử biến động và đối chiếu số dư.
        </div>
    @endif
@endsection
