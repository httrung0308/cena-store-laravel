<?php

namespace App\Services;

use App\Enums\ClassificationGroup;
use App\Models\Classification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ClassificationManagementService
{
    /** @return array<string, mixed> */
    public function rules(?Classification $classification = null, ?string $group = null): array
    {
        $groupValue = $group ?? $classification?->classification_group?->value;

        return [
            'name' => ['required', 'string', 'max:100'],
            'classification_group' => ['required', Rule::enum(ClassificationGroup::class)],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
                Rule::unique('classifications', 'slug')
                    ->where(fn ($query) => $groupValue ? $query->where('classification_group', $groupValue) : $query)
                    ->ignore($classification?->id),
            ],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param array<string, mixed> $input */
    public function save(User $actor, ?Classification $classification, array $input): Classification
    {
        $group = $input['classification_group'] ?? null;
        $data = Validator::make($input, $this->rules($classification, $group))->validate();

        return DB::transaction(function () use ($classification, $data): Classification {
            $current = $classification
                ? Classification::whereKey($classification->id)->lockForUpdate()->firstOrFail()
                : new Classification;

            if ($current->exists && $current->products()->exists() && $current->classification_group->value !== $data['classification_group']) {
                throw ValidationException::withMessages([
                    'classification_group' => 'Không thể đổi nhóm của nhãn đang có sản phẩm sử dụng.',
                ]);
            }

            $current->forceFill($data)->save();

            return $current->refresh();
        });
    }

    public function toggleActive(User $actor, Classification $classification): Classification
    {
        return DB::transaction(function () use ($classification): Classification {
            $current = Classification::whereKey($classification->id)->lockForUpdate()->firstOrFail();
            $current->forceFill(['is_active' => ! $current->is_active])->save();

            return $current->refresh();
        });
    }

    public function delete(User $actor, Classification $classification): void
    {
        DB::transaction(function () use ($classification): void {
            $current = Classification::whereKey($classification->id)->lockForUpdate()->firstOrFail();

            if ($current->products()->exists()) {
                throw ValidationException::withMessages([
                    'classification' => 'Không thể xóa nhãn đang có sản phẩm liên kết. Vui lòng chuyển sang trạng thái Tạm ngưng.',
                ]);
            }

            $current->delete();
        });
    }
}
