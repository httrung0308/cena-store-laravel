@props(['action' => null, 'category' => [], 'parents' => [], 'genderOptions' => [], 'method' => 'POST'])
<form @if ($action) action="{{ $action }}" @endif method="POST" class="space-y-5">
    @csrf
    @if (in_array(strtoupper($method), ['PATCH', 'PUT'], true)) @method(strtoupper($method)) @endif
    <x-forms.text-field name="name" label="Tên danh mục" :value="$category['name'] ?? ''" required />
    <x-forms.text-field name="slug" label="Slug" :value="$category['slug'] ?? ''" required />
    <div><label for="category-parent" class="text-lg font-semibold">Danh mục cha (tối đa 2 cấp)</label><select id="category-parent" name="parent_id" class="input block w-full"><option value="">Không có</option>
        @foreach ($parents as $parent)<option value="{{ $parent['id'] }}" @selected((string) old('parent_id', $category['parent_id'] ?? '') === (string) $parent['id'])>{{ $parent['name'] }}</option>@endforeach
    </select><x-forms.field-error name="parent_id" /></div>
    <div><label for="category-gender" class="text-lg font-semibold">Nhóm</label><select id="category-gender" name="gender_group" class="input block w-full" required><option value="">Chọn nhóm</option>
        @foreach ($genderOptions as $item)<option value="{{ $item['value'] }}" @selected(old('gender_group', $category['gender_group'] ?? '') === $item['value'])>{{ $item['label'] }}</option>@endforeach
    </select><x-forms.field-error name="gender_group" /></div>
    <x-forms.text-field name="display_order" label="Thứ tự" :value="$category['display_order'] ?? 1" type="number" />
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category['is_active'] ?? true))> Hiển thị</label>
    <x-ui.button type="submit" :disabled="! $action">Lưu danh mục</x-ui.button>
    @unless ($action)<p role="status">Chưa có quyền lưu danh mục.</p>@endunless
</form>
