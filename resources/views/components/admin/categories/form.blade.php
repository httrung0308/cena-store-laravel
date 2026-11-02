@props(['action' => null, 'category' => [], 'parents' => [], 'genderOptions' => [], 'method' => 'POST'])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="admin-card space-y-5">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    <h2 class="text-xl font-semibold">Thông tin danh mục</h2>
    <div class="grid gap-4 md:grid-cols-2">
        <x-admin.field name="name" label="Tên danh mục" :value="$category['name'] ?? ''" required />
        <x-admin.field name="slug" label="Slug" :value="$category['slug'] ?? ''" required />
        <div><label for="category-parent" class="admin-label">Danh mục cha (tối đa 2 cấp)</label><select id="category-parent" name="parent_id" class="input w-full border-zinc-300"><option value="">Không có</option>
            @foreach ($parents as $parent)<option value="{{ $parent['id'] }}" @selected((string) old('parent_id', $category['parent_id'] ?? '') === (string) $parent['id'])>{{ $parent['name'] }}</option>@endforeach
        </select><x-forms.field-error name="parent_id" /></div>
        <div><label for="category-gender" class="admin-label">Nhóm <span class="text-red-500">*</span></label><select id="category-gender" name="gender_group" class="input w-full border-zinc-300" required><option value="">Chọn nhóm</option>
            @foreach ($genderOptions as $item)<option value="{{ $item['value'] }}" @selected(old('gender_group', $category['gender_group'] ?? '') === $item['value'])>{{ $item['label'] }}</option>@endforeach
        </select><x-forms.field-error name="gender_group" /></div>
        <x-admin.field name="display_order" label="Thứ tự" :value="$category['display_order'] ?? 1" type="number" />
    </div>
    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" class="native-checkbox accent-black" @checked(old('is_active', $category['is_active'] ?? true))> Hiển thị</label>
    <button type="submit" class="btn-pill-dark" @disabled(! $action)>Lưu danh mục</button>
    @unless ($action)<p role="status">Chưa có quyền lưu danh mục.</p>@endunless
</form>
