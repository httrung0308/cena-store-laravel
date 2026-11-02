<?php

namespace App\Services;

use App\Enums\GenderGroup;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProductManagementTaxonomyService
{
    /** @return array<string, mixed> */
    public function categoryRules(?Category $category = null): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'gender_group' => ['required', Rule::enum(GenderGroup::class)], 'parent_id' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:10000'], 'display_order' => ['required', 'integer', 'between:0,65535'], 'is_active' => ['required', 'boolean']];
    }

    /** @return array<string, mixed> */
    public function supplierRules(?Supplier $supplier = null): array
    {
        return ['code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/D', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'name' => ['required', 'string', 'max:150'], 'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+() .-]{6,20}$/D'],
            'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'is_active' => ['required', 'boolean']];
    }

    /** @param array<string, mixed> $input */
    public function saveCategory(User $actor, ?Category $category, array $input): Category
    {
        try {
            return DB::transaction(function () use ($actor, $category, $input): Category {
                ProductManagementAccess::lock($actor);
                $all = Category::withTrashed()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $current = $category ? $all->get($category->id) : new Category;
                abort_unless($current && ! $current->trashed(), 404);
                $data = Validator::make($input, $this->categoryRules($category))->validate();
                $parent = empty($data['parent_id']) ? null : $all->get((int) $data['parent_id']);
                if (! empty($data['parent_id']) && (! $parent || $parent->trashed() || $parent->parent_id !== null || $parent->id === $current->id || $parent->gender_group->value !== $data['gender_group'])) {
                    throw ValidationException::withMessages(['parent_id' => 'Danh mục cha phải là cấp 1, cùng nhóm giới tính; không được tạo vòng lặp.']);
                }
                foreach ($all as $child) {
                    if ($current->exists && $child->parent_id === $current->id && ($parent || $child->gender_group->value !== $data['gender_group'])) {
                        throw ValidationException::withMessages(['parent_id' => 'Không thể chuyển cấp/nhóm khi còn danh mục con.']);
                    }
                }
                $data['parent_id'] = $parent?->id;
                $current->forceFill($data)->save();

                return $current->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Slug đã được sử dụng.']);
        }
    }

    /** @param array<string, mixed> $input */
    public function saveSupplier(User $actor, ?Supplier $supplier, array $input): Supplier
    {
        try {
            return DB::transaction(function () use ($actor, $supplier, $input): Supplier {
                ProductManagementAccess::lock($actor);
                $current = $supplier ? Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail() : new Supplier;
                $data = Validator::make($input, $this->supplierRules($current))->validate();
                $current->forceFill($data)->save();

                return $current->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Mã nhà cung cấp đã được sử dụng.']);
        }
    }

    public function deactivate(User $actor, Category|Supplier $record): void
    {
        DB::transaction(function () use ($actor, $record): void {
            ProductManagementAccess::lock($actor);
            $record::whereKey($record->id)->lockForUpdate()->firstOrFail()->forceFill(['is_active' => false])->save();
        }, 3);
    }
}
