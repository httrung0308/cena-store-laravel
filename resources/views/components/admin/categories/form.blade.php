@props(['action' => null, 'category' => [], 'parents' => [], 'genderOptions' => [], 'method' => 'POST'])
@php
    $isNew = empty($category['id']);
    $initialName = old('name', $category['name'] ?? '');
    $initialSlug = old('slug', $category['slug'] ?? '');
    $initialGender = old('gender_group', $category['gender_group'] ?? '');
@endphp
<form @if ($action) action="{{ $action }}" @endif method="POST" class="admin-card space-y-5"
      x-data="{
          ...liveSlug({ isNew: {{ $isNew ? 'true' : 'false' }}, initialName: @js($initialName), initialSlug: @js($initialSlug) }),
          selectedGender: @js($initialGender)
      }">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    @if (filled(old('parent_id', $category['parent_id'] ?? null)))
        <input type="hidden" name="parent_id" value="{{ old('parent_id', $category['parent_id'] ?? '') }}">
    @endif
    <h2 class="text-base font-bold text-main">Thông tin đối tượng</h2>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="category-name" class="admin-label">Tên đối tượng <span class="text-error">*</span></label>
            <input id="category-name" name="name" type="text" x-model="name" x-on:input="onNameInput"
                   class="input w-full border-border focus:border-accent text-main text-xs @error('name') border-error @enderror"
                   placeholder="Ví dụ: Giày Chạy Bộ" required>
            <x-forms.field-error name="name" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="category-slug" class="admin-label">Slug (Đường dẫn) <span class="text-error">*</span></label>
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
                <input id="category-slug" name="slug" type="text" x-ref="slugInput" x-model="slug" x-on:input="onSlugInput"
                       x-bind:readonly="isLocked"
                       x-bind:class="isLocked ? 'bg-surface-alt/70 cursor-not-allowed opacity-85' : 'bg-surface'"
                       class="input w-full border-border focus:border-accent text-main font-mono text-xs @error('slug') border-error @enderror"
                       placeholder="giay-chay-bo" required>
            </div>
            <p class="mt-1 text-[11px] text-muted">
                <span x-show="isNew && !manuallyModified">Tự động tạo từ tên. Bạn có thể sửa tay.</span>
                <span x-show="isNew && manuallyModified">Đã sửa tay (không tự ghi đè khi đổi tên).</span>
                <span x-show="!isNew && isLocked">Được khóa bảo vệ liên kết URL. Bấm "Mở khóa" nếu cần đổi.</span>
            </p>
            <x-forms.field-error name="slug" />
        </div>

        <div>
            <label for="category-gender" class="admin-label text-muted">Nhóm đối tượng <span class="text-error">*</span></label>
            <select id="category-gender" name="gender_group" x-model="selectedGender"
                    class="input w-full border-border bg-surface text-main text-xs @error('gender_group') border-error @enderror" required>
                <option value="">-- Chọn nhóm --</option>
                @foreach ($genderOptions as $item)
                    <option value="{{ $item['value'] }}">{{ $item['label'] }}</option>
                @endforeach
            </select>
            <x-forms.field-error name="gender_group" />
        </div>

        <x-admin.field name="display_order" label="Thứ tự hiển thị" :value="$category['display_order'] ?? 1" type="number" min="0" max="65535" hint="Số nhỏ hơn sẽ xếp trước." />
    </div>

    <label class="flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
        <input type="checkbox" name="is_active" value="1" class="native-checkbox accent-accent rounded" @checked(old('is_active', $category['is_active'] ?? true))>
        <span>Hiển thị đối tượng này</span>
    </label>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md" @disabled(! $action)>Lưu đối tượng</button>
        <a href="{{ route('admin.categories.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Hủy</a>
    </div>
    @unless ($action)<p role="status" class="text-xs text-error">Chưa có quyền lưu đối tượng.</p>@endunless
</form>
