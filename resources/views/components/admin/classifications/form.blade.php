@props([
    'action' => null,
    'record' => null,
    'groups' => [],
    'productsCount' => 0,
    'homeNavCounts' => [],
    'homeNavLimit' => 6,
    'method' => 'POST',
])
@php
    $silCount = (int) ($homeNavCounts['SILHOUETTE'] ?? 0);
    $actCount = (int) ($homeNavCounts['ACTIVITY'] ?? 0);
    $isNew = empty($record?->id);
    $initialName = old('name', $record->name ?? '');
    $initialSlug = old('slug', $record->slug ?? '');
@endphp
<form @if ($action) action="{{ $action }}" @endif method="POST" class="admin-card space-y-6"
      x-data="liveSlug({ isNew: {{ $isNew ? 'true' : 'false' }}, initialName: @js($initialName), initialSlug: @js($initialSlug) })">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif

    <div>
        <h2 class="text-base font-bold text-main">Thông tin nhãn phân loại</h2>
        <p class="text-xs text-muted mt-1">Phân loại sản phẩm đa trị giúp khách hàng dễ dàng khám phá theo kiểu dáng và mục đích sử dụng.</p>
    </div>

    @if ($productsCount > 0)
        <div class="rounded-xl border border-warning/30 bg-warning-bg p-4 text-xs text-warning-text">
            <strong class="font-bold">Lưu ý:</strong> Nhãn này đang được gán cho <strong>{{ $productsCount }}</strong> sản phẩm. Để đảm bảo tính toàn vẹn dữ liệu, không thể thay đổi nhóm phân loại.
        </div>
    @endif

    <div class="space-y-4">
        <div>
            <label for="classification-name" class="admin-label text-muted">Tên nhãn <span class="text-error">*</span></label>
            <input type="text" id="classification-name" name="name" x-model="name" x-on:input="onNameInput" class="input w-full border-border bg-surface text-main text-xs @error('name') border-error @enderror" placeholder="Ví dụ: Sneaker, Chạy bộ..." required>
            <x-forms.field-error name="name" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="classification-slug" class="admin-label text-muted">Slug (Đường dẫn tĩnh) <span class="text-error">*</span></label>
                <template x-if="isLocked">
                    <button type="button" x-on:click="unlockSlug" class="text-[11px] font-semibold text-accent hover:underline inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>Mở khóa sửa slug</span>
                    </button>
                </template>
                <template x-if="!isLocked && isNew">
                    <button type="button" x-on:click="regenerateSlug" class="text-[11px] text-muted hover:text-main inline-flex items-center gap-1">
                        <span>Làm mới theo tên</span>
                    </button>
                </template>
            </div>
            <div class="relative mt-1">
                <input type="text" id="classification-slug" name="slug" x-ref="slugInput" x-model="slug" x-on:input="onSlugInput"
                       x-bind:readonly="isLocked"
                       x-bind:class="isLocked ? 'bg-surface-alt/70 cursor-not-allowed opacity-85' : 'bg-surface'"
                       class="input w-full border-border bg-surface text-main text-xs font-mono @error('slug') border-error @enderror" placeholder="vi-du: sneaker, chay-bo..." required>
            </div>
            <p class="text-[11px] text-muted/70 mt-1">
                <span x-show="isNew && !manuallyModified">Tự động gợi ý từ tên nhãn. Bạn có thể sửa tay.</span>
                <span x-show="isNew && manuallyModified">Đã chỉnh sửa thủ công (không tự ghi đè khi đổi tên).</span>
                <span x-show="!isNew && isLocked">Được khóa bảo vệ liên kết. Bấm "Mở khóa" nếu cần sửa.</span>
            </p>
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
            <p class="text-[11px] text-muted/70 mt-1">Số nhỏ hơn sẽ ưu tiên xếp trước trong menu Header, sidebar trang chủ, trang toàn bộ và bộ lọc.</p>
            <x-forms.field-error name="sort_order" />
        </div>

        <div>
            <label for="classification-description" class="admin-label text-muted">Mô tả / Ghi chú</label>
            <textarea id="classification-description" name="description" rows="3" class="input w-full border-border bg-surface text-main text-xs" placeholder="Ghi chú ngắn về đặc trưng của nhãn này...">{{ old('description', $record->description ?? '') }}</textarea>
            <x-forms.field-error name="description" />
        </div>

        <div class="space-y-3 pt-2 border-t border-border/70">
            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2.5 text-xs font-semibold text-main cursor-pointer select-none">
                    <input type="checkbox" name="is_active" value="1" class="native-checkbox text-accent focus:ring-accent rounded" @checked(old('is_active', $record->exists ? $record->is_active : true))>
                    <span>Kích hoạt nhãn này (Hiển thị trong trang Danh mục &amp; Phân loại, bộ lọc Storefront và cho phép gán cho sản phẩm)</span>
                </label>
                <x-forms.field-error name="is_active" />
            </div>

            <div class="rounded-xl border border-border bg-surface/60 p-3.5 space-y-1.5">
                <input type="hidden" name="show_in_home_nav" value="0">
                <label class="flex items-start gap-2.5 text-xs font-semibold text-main cursor-pointer select-none">
                    <input type="checkbox" name="show_in_home_nav" value="1" class="native-checkbox mt-0.5 text-accent focus:ring-accent rounded" @checked(old('show_in_home_nav', $record->exists ? $record->show_in_home_nav : false))>
                    <span>Hiển thị ở menu &amp; trang chủ</span>
                </label>
                <p class="text-[11px] text-muted leading-relaxed pl-6">
                    Giới hạn tối đa <strong>{{ $homeNavLimit }} mục đang hoạt động</strong> mỗi nhóm (hiện có: Kiểu dáng <strong>{{ $silCount }}/{{ $homeNavLimit }}</strong> · Mục đích <strong>{{ $actCount }}/{{ $homeNavLimit }}</strong>). Nhãn đang tạm ngưng sẽ không hiển thị ra Storefront.
                </p>
                <x-forms.field-error name="show_in_home_nav" />
            </div>
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
