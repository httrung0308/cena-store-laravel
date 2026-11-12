@props(['colors' => []])
@php
    $colors = array_map(fn($c) => array_merge($c, [
        'name' => $c['color_name'] ?? $c['name'] ?? '',
        'hex' => $c['primary_hex'] ?? $c['hex'] ?? '#000000',
    ]), is_array($colors) ? $colors : []);
@endphp
<section class="admin-card space-y-5" x-data="colorVariants({ colors: @js($colors) })" aria-label="Phối màu và size">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-main">Phối màu & Size sản phẩm</h2>
            <p class="text-xs text-muted mt-0.5">Quản lý các màu sắc, dải size và mã SKU cho từng biến thể.</p>
        </div>
        <button type="button" class="px-4 py-2 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-sm"
                x-on:click="addColor({ is_active: true, color_key: 'black', hex: '#000000', images: [] })">
            + Thêm phối màu
        </button>
    </div>

    <template x-for="(color, ci) in colors.filter(c => !c.removed)" :key="color.uid">
        <fieldset class="min-w-0 space-y-4 rounded-xl border border-border p-5 bg-surface/30">
            <legend class="px-2 font-bold text-main text-xs uppercase tracking-wide" x-text="color.name ? (color.name + ' (' + (color.style_color_code || 'Chưa có mã') + ')') : 'Phối màu mới'"></legend>
            <input type="hidden" x-bind:name="'colors['+ci+'][id]'" x-bind:value="color.id || ''">

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="text-xs font-semibold text-muted">Tên màu <span class="text-error">*</span></label>
                    <input class="input mt-1 w-full border-border bg-surface text-xs font-normal text-main"
                           x-model="color.name" x-bind:name="'colors['+ci+'][color_name]'" placeholder="Ví dụ: Đen Trắng, Trắng Cam" required>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted">Mã phối màu (Style Code) <span class="text-error">*</span></label>
                    <input class="input mt-1 w-full border-border bg-surface text-xs font-normal text-main font-mono uppercase"
                           x-model="color.style_color_code" x-bind:name="'colors['+ci+'][style_color_code]'" placeholder="Ví dụ: BLK, CW2288" required>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted">Nhóm màu</label>
                    <select class="input mt-1 w-full border-border bg-surface text-xs font-normal text-main"
                            x-model="color.color_key" x-bind:name="'colors['+ci+'][color_key]'">
                        @foreach(config('store.palette') as $key => $option)
                            <option value="{{ $key }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted">Mã màu HEX <span class="text-error">*</span></label>
                    <div class="flex items-center gap-2 mt-1">
                        <input type="color" class="h-9 w-9 rounded-lg border border-border cursor-pointer bg-surface p-0.5"
                               x-model="color.hex">
                        <input class="input w-full border-border bg-surface text-xs font-normal text-main font-mono uppercase"
                               x-model="color.hex" x-bind:name="'colors['+ci+'][primary_hex]'" pattern="#[A-Fa-f0-9]{6}" required>
                    </div>
                </div>
            </div>

            <input type="hidden" x-bind:name="'colors['+ci+'][is_active]'" x-bind:value="Number(color.is_active ?? 1) ? 1 : 0">
            <label class="inline-flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                <input type="checkbox" class="native-checkbox text-accent focus:ring-accent rounded" x-model="color.is_active">
                <span>Đang mở bán phối màu này</span>
            </label>

            {{-- Variants section --}}
            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-main uppercase">Danh sách size & SKU</h3>
                    <button type="button" class="text-xs font-bold text-accent hover:underline"
                            x-on:click="addVariant(color.uid, { size_value: '', sku: '', is_active: true })">
                        + Thêm size riêng
                    </button>
                </div>

                <div class="space-y-2">
                    <template x-for="(variant, vi) in color.variants.filter(v => !v.removed)" :key="variant.uid">
                        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-surface p-3 border"
                             x-bind:class="isDuplicateSku(variant.sku, variant.uid) ? 'border-error bg-error-bg/30' : 'border-border/60'">
                            <input type="hidden" x-bind:name="'colors['+ci+'][variants]['+vi+'][id]'" x-bind:value="variant.id || ''">

                            <div class="w-20">
                                <label class="text-[11px] font-semibold text-muted block">Size <span class="text-error">*</span></label>
                                <input class="input mt-1 w-full border-border bg-surface text-xs font-normal text-main"
                                       x-model="variant.size_value" x-on:input="onSizeChange(color.uid, variant.uid)"
                                       x-bind:name="'colors['+ci+'][variants]['+vi+'][size_value]'" placeholder="40" required>
                            </div>

                            <div class="flex-1 min-w-[180px]">
                                <div class="flex items-center justify-between">
                                    <label class="text-[11px] font-semibold text-muted">Mã SKU</label>
                                    <span x-show="isDuplicateSku(variant.sku, variant.uid)" class="text-[10px] font-bold text-error">Trùng SKU!</span>
                                </div>
                                <input class="input mt-1 w-full border-border bg-surface text-xs font-mono font-normal text-main uppercase"
                                       x-bind:class="isDuplicateSku(variant.sku, variant.uid) ? 'border-error ring-1 ring-error' : ''"
                                       x-model="variant.sku" x-on:input="onSkuChange(variant)"
                                       x-bind:name="'colors['+ci+'][variants]['+vi+'][sku]'" placeholder="Tự sinh nếu để trống">
                            </div>

                            <div class="w-32">
                                <label class="text-[11px] font-semibold text-muted block">Giá riêng (₫)</label>
                                <input class="input mt-1 w-full border-border bg-surface text-xs font-mono font-normal text-main"
                                       inputmode="numeric" x-model="variant.price_override"
                                       x-bind:name="'colors['+ci+'][variants]['+vi+'][price_override]'" placeholder="Kế thừa">
                            </div>

                            <input type="hidden" x-bind:name="'colors['+ci+'][variants]['+vi+'][is_active]'" x-bind:value="Number(variant.is_active ?? 1) ? 1 : 0">
                            <label class="inline-flex items-center gap-1.5 text-xs text-main cursor-pointer pt-4">
                                <input type="checkbox" class="native-checkbox text-accent focus:ring-accent rounded" x-model="variant.is_active">
                                <span>Bán</span>
                            </label>

                            <button type="button" class="text-xs font-bold text-error hover:underline pt-4 ml-auto"
                                    x-on:click="removeVariant(color.uid, variant.uid)">
                                Bỏ size
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Quick sequence generator --}}
            <details class="rounded-xl border border-border p-3 bg-surface-alt/20">
                <summary class="cursor-pointer text-xs font-semibold text-main hover:text-accent">
                    ⚡ Sinh nhanh dải size (chỉ dùng khi chưa nhập SKU riêng)
                </summary>
                <div class="grid gap-3 py-3 sm:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold text-muted">Từ size (ví dụ: 39)</label>
                        <input class="input mt-1 w-full border-border bg-surface text-xs font-normal text-main" x-bind:name="'colors['+ci+'][range_start]'" placeholder="39">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted">Đến size (ví dụ: 43)</label>
                        <input class="input mt-1 w-full border-border bg-surface text-xs font-normal text-main" x-bind:name="'colors['+ci+'][range_end]'" placeholder="43">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-muted">Tiền tố SKU (Tùy chọn)</label>
                        <input class="input mt-1 w-full border-border bg-surface text-xs font-normal text-main font-mono uppercase" x-bind:name="'colors['+ci+'][sku_prefix]'" placeholder="Để trống hệ thống tự sinh">
                    </div>
                </div>
                <p class="text-[11px] text-muted">Hệ thống tự sinh từng nửa size (ví dụ: 40 → 41 sẽ tạo 40, 40.5, 41) và tự động gắn mã SKU ổn định duy nhất.</p>
            </details>

            <x-admin.products.images dynamic />

            <div class="pt-2 border-t border-border/60 flex justify-end">
                <button type="button" class="text-xs font-bold text-error hover:underline" x-on:click="removeColor(color.uid)">
                    Xóa phối màu này
                </button>
            </div>
        </fieldset>
    </template>

    <p class="text-xs text-muted">Lưu ý: Tồn kho và giá vốn được nhập thông qua Phiếu Nhập Kho. Bỏ màu hoặc size sẽ ngừng bán biến thể đó nhưng vẫn bảo toàn lịch sử giao dịch và chứng từ.</p>
</section>
