@extends('layouts.admin')
@section('title', $record->exists ? 'Sửa Danh Mục' : 'Thêm Danh Mục')
@section('eyebrow', 'QUẢN TRỊ DANH MỤC')
@section('actions')
    <a class="px-4 py-2 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors" href="{{ route('admin.categories.index') }}">← Quay lại danh sách</a>
@endsection
@php
    $action = $record->exists ? route('admin.categories.update', $record) : route('admin.categories.store');
    $method = $record->exists ? 'PUT' : 'POST';
    $category = $record->toArray();
    $isNew = empty($category['id']);
    $initialName = old('name', $category['name'] ?? '');
    $initialSlug = old('slug', $category['slug'] ?? '');
    $initialGender = old('gender_group', $category['gender_group'] ?? '');
    $initialParent = (string) old('parent_id', $category['parent_id'] ?? '');
    $hasSlugError = $errors->has('slug');
@endphp
@section('content')
    <div class="max-w-3xl mx-auto">
        <form action="{{ $action }}" method="POST" class="admin-card space-y-5"
              x-data="{
                  ...liveSlug({ isNew: {{ $isNew ? 'true' : 'false' }}, initialName: @js($initialName), initialSlug: @js($initialSlug) }),
                  showSlugEditor: {{ $hasSlugError ? 'true' : 'false' }},
                  allParents: @js($parents),
                  selectedGender: @js($initialGender),
                  selectedParent: @js($initialParent),
                  get filteredParents() {
                      if (!this.selectedGender) return [];
                      return this.allParents.filter(p => p.gender_group === this.selectedGender);
                  },
                  onGenderChange() {
                      const valid = this.filteredParents.some(p => String(p.id) === String(this.selectedParent));
                      if (!valid) {
                          this.selectedParent = '';
                      }
                  }
              }">
            @csrf
            @if ($method === 'PUT') @method('PUT') @endif

            <h2 class="text-base font-bold text-main">Thông tin danh mục</h2>

            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-12">
                    <label for="category-name" class="admin-label">Tên danh mục <span class="text-error">*</span></label>
                    <input id="category-name" name="name" type="text" x-model="name" x-on:input="onNameInput"
                           class="input w-full border-border focus:border-accent text-main text-xs @error('name') border-error @enderror"
                           placeholder="Ví dụ: Giày Chạy Bộ" required>
                    <x-forms.field-error name="name" />

                    {{-- Xem trước Slug thu gọn + nút Tùy chỉnh slug --}}
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted">
                        <span class="truncate">
                            Slug: <code class="font-mono text-main" x-text="slug || '...'"></code>
                        </span>
                        <button type="button" @click="showSlugEditor = !showSlugEditor"
                                class="font-semibold text-accent hover:underline shrink-0">
                            <span x-text="showSlugEditor ? 'Ẩn chỉnh sửa slug' : 'Tùy chỉnh slug'"></span>
                        </button>
                    </div>

                    <div x-show="showSlugEditor" x-cloak class="mt-2.5 rounded-xl border border-border bg-surface/60 p-3 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="category-slug" class="text-xs font-semibold text-main">Đường dẫn slug <span class="text-error">*</span></label>
                            <div class="flex items-center gap-3">
                                <button type="button" x-show="isLocked" @click="unlockSlug()"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-accent hover:underline">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    <span>Mở khóa sửa slug</span>
                                </button>
                                <button type="button" x-show="!isLocked && isNew" @click="regenerateSlug()"
                                        class="text-[11px] font-semibold text-muted hover:text-main">
                                    Làm mới theo tên
                                </button>
                            </div>
                        </div>
                        <input id="category-slug" name="slug" type="text" x-ref="slugInput" x-model="slug" x-on:input="onSlugInput"
                               x-bind:readonly="isLocked"
                               x-bind:class="isLocked ? 'bg-surface-alt/70 cursor-not-allowed opacity-85' : 'bg-surface'"
                               class="input w-full border-border focus:border-accent text-main font-mono text-xs @error('slug') border-error @enderror"
                               placeholder="giay-chay-bo" required>
                        <p class="text-[11px] text-muted">
                            <span x-show="isNew && !manuallyModified">Tự tạo từ tên danh mục. Nếu sửa tay, hệ thống sẽ giữ nguyên không ghi đè.</span>
                            <span x-show="isNew && manuallyModified">Đã sửa tay (không tự ghi đè khi đổi tên).</span>
                            <span x-show="!isNew && isLocked">Đang khóa để giữ nguyên liên kết cũ khi đổi tên danh mục.</span>
                            <span x-show="!isNew && !isLocked" class="text-warning-text font-medium">Lưu ý: Đổi slug danh mục có thể làm thay đổi đường dẫn đã chia sẻ.</span>
                        </p>
                        <x-forms.field-error name="slug" />
                    </div>
                </div>

                <div class="md:col-span-4">
                    <label for="category-gender" class="admin-label">Nhóm giới tính <span class="text-error">*</span></label>
                    <select id="category-gender" name="gender_group" x-model="selectedGender" x-on:change="onGenderChange"
                            class="input w-full border-border bg-surface text-main text-xs @error('gender_group') border-error @enderror" required>
                        <option value="">-- Chọn nhóm --</option>
                        @foreach ($genderOptions as $item)
                            <option value="{{ $item['value'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    <x-forms.field-error name="gender_group" />
                </div>

                <div class="md:col-span-5">
                    <label for="category-parent" class="admin-label">Danh mục cha</label>
                    <select id="category-parent" name="parent_id" x-model="selectedParent"
                            class="input w-full border-border bg-surface text-main text-xs @error('parent_id') border-error @enderror">
                        <option value="">Không có (Danh mục gốc)</option>
                        <template x-for="parent in filteredParents" :key="parent.id">
                            <option :value="parent.id" x-text="parent.name" :selected="String(parent.id) === String(selectedParent)"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-[11px] text-muted">Chỉ hiển thị danh mục gốc thuộc nhóm giới tính đã chọn.</p>
                    <x-forms.field-error name="parent_id" />
                </div>

                <div class="md:col-span-3">
                    <x-admin.field name="display_order" label="Thứ tự hiển thị" :value="$category['display_order'] ?? 1" type="number" min="0" max="65535" hint="Số nhỏ xếp trước." />
                </div>
            </div>

            <label class="flex items-center gap-2 text-xs font-semibold text-main cursor-pointer select-none">
                <input type="checkbox" name="is_active" value="1" class="native-checkbox accent-accent rounded" @checked(old('is_active', $category['is_active'] ?? true))>
                <span>Hiển thị danh mục này</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-accent-text text-xs font-bold rounded-xl transition-colors shadow-md">Lưu danh mục</button>
                <a href="{{ route('admin.categories.index') }}" class="px-6 py-2.5 bg-surface hover:bg-border/40 text-muted hover:text-main text-xs font-bold rounded-xl border border-border transition-colors">Hủy</a>
            </div>
        </form>
    </div>
@endsection
