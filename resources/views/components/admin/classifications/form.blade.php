@props(['action' => null, 'record' => null, 'groups' => [], 'productsCount' => 0, 'method' => 'POST'])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="admin-card space-y-6">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    
    <div>
        <h2 class="text-base font-bold text-main">Thông tin nhãn phân loại</h2>
        <p class="text-xs text-muted mt-1">Phân loại sản phẩm đa trị giúp khách hàng dễ dàng khám phá theo kiểu dáng và mục đích sử dụng.</p>
    </div>

    @if ($productsCount > 0)
        <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-xs text-amber-700 dark:text-amber-400">
            <strong class="font-bold">Lưu ý:</strong> Nhãn này đang được gán cho <strong>{{ $productsCount }}</strong> sản phẩm. Để đảm bảo tính toàn vẹn dữ liệu, không thể thay đổi nhóm phân loại.
        </div>
    @endif

    <div class="space-y-4">
        <div>
            <label for="classification-name" class="admin-label text-muted">Tên nhãn <span class="text-error">*</span></label>
            <input type="text" id="classification-name" name="name" value="{{ old('name', $record->name ?? '') }}" class="input w-full border-border bg-surface text-main text-xs" placeholder="Ví dụ: Sneaker, Chạy bộ..." required>
            <x-forms.field-error name="name" />
        </div>

        <div>
            <label for="classification-slug" class="admin-label text-muted">Slug (Đường dẫn tĩnh) <span class="text-error">*</span></label>
            <input type="text" id="classification-slug" name="slug" value="{{ old('slug', $record->slug ?? '') }}" class="input w-full border-border bg-surface text-main text-xs font-mono" placeholder="vi-du: sneaker, chay-bo..." required>
            <p class="text-[11px] text-muted/70 mt-1">Chỉ gồm chữ cái thường không dấu, số và dấu gạch ngang.</p>
            <x-forms.field-error name="slug" />
        </div>

        <div>
            <label for="classification-group" class="admin-label text-muted">Nhóm phân loại <span class="text-error">*</span></label>
            @if ($productsCount > 0)
                <input type="hidden" name="classification_group" value="{{ $record->classification_group->value }}">
                <div class="input w-full border-border bg-surface/50 text-muted text-xs cursor-not-allowed">
                    {{ $record->classification_group->label() }} (Đã cố định do có sản phẩm đang dùng)
                </div>
            @else
                <select id="classification-group" name="classification_group" class="input w-full border-border bg-surface text-main text-xs" required>
                    <option value="">-- Chọn nhóm phân loại --</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group['value'] }}" @selected(old('classification_group', $record->classification_group?->value ?? '') === $group['value'])>
                            {{ $group['label'] }}
                        </option>
                    @endforeach
                </select>
            @endif
            <x-forms.field-error name="classification_group" />
        </div>

        <div>
            <label for="classification-sort-order" class="admin-label text-muted">Thứ tự hiển thị <span class="text-error">*</span></label>
            <input type="number" id="classification-sort-order" name="sort_order" value="{{ old('sort_order', $record->sort_order ?? 1) }}" min="0" max="65535" class="input w-full border-border bg-surface text-main text-xs font-mono" required>
            <p class="text-[11px] text-muted/70 mt-1">Số nhỏ hơn sẽ ưu tiên hiển thị trước trên bộ lọc.</p>
            <x-forms.field-error name="sort_order" />
        </div>

        <div>
            <label for="classification-description" class="admin-label text-muted">Mô tả / Ghi chú</label>
            <textarea id="classification-description" name="description" rows="3" class="input w-full border-border bg-surface text-main text-xs" placeholder="Ghi chú ngắn về đặc trưng của nhãn này...">{{ old('description', $record->description ?? '') }}</textarea>
            <x-forms.field-error name="description" />
        </div>

        <div class="pt-2">
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-2.5 text-xs font-semibold text-main cursor-pointer select-none">
                <input type="checkbox" name="is_active" value="1" class="native-checkbox text-accent focus:ring-accent rounded" @checked(old('is_active', $record->exists ? $record->is_active : true))>
                <span>Kích hoạt nhãn này (Hiển thị trong bộ lọc Storefront và cho phép gán cho sản phẩm)</span>
            </label>
            <x-forms.field-error name="is_active" />
        </div>
    </div>

    <div class="flex items-center gap-3 pt-3 border-t border-border">
        <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md" @disabled(! $action)>
            {{ $record->exists ? 'Cập nhật nhãn' : 'Lưu nhãn' }}
        </button>
        <a href="{{ route('admin.classifications.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">
            Hủy
        </a>
    </div>
</form>
