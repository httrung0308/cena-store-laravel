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
    public const MAX_HOME_NAV_PER_GROUP = 6;

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
            'show_in_home_nav' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function activeHomeNavCountsByGroup(): array
    {
        $counts = Classification::query()
            ->where('is_active', true)
            ->where('show_in_home_nav', true)
            ->selectRaw('classification_group, COUNT(*) as total')
            ->groupBy('classification_group')
            ->pluck('total', 'classification_group')
            ->all();

        $result = [];
        foreach (ClassificationGroup::cases() as $group) {
            $result[$group->value] = (int) ($counts[$group->value] ?? 0);
        }

        return $result;
    }

    /** @param array<string, mixed> $input */
    public function save(User $actor, ?Classification $classification, array $input): Classification
    {
        $group = $input['classification_group'] ?? null;
        $data = Validator::make($input, $this->rules($classification, $group))->validate();

        return DB::transaction(function () use ($classification, $data): Classification {
            // Khóa nhóm đích theo thứ tự id tăng dần trước khi kiểm tra giới hạn 6 mục hiển thị
            $this->lockGroupRows($data['classification_group']);

            $current = $classification
                ? Classification::whereKey($classification->id)->lockForUpdate()->firstOrFail()
                : new Classification;

            if ($current->exists && $current->products()->exists() && $current->classification_group->value !== $data['classification_group']) {
                throw ValidationException::withMessages([
                    'classification_group' => 'Không thể đổi nhóm của nhãn đang có sản phẩm sử dụng.',
                ]);
            }

            $showInHomeNav = array_key_exists('show_in_home_nav', $data)
                ? (bool) $data['show_in_home_nav']
                : ($current->exists ? (bool) $current->show_in_home_nav : false);

            if ((bool) $data['is_active'] && $showInHomeNav) {
                $this->assertWithinHomeNavLimit(
                    $data['classification_group'],
                    $current->exists ? $current->id : null,
                    'show_in_home_nav'
                );
            }

            $data['show_in_home_nav'] = $showInHomeNav;
            $current->forceFill($data)->save();

            return $current->refresh();
        });
    }

    public function toggleActive(User $actor, Classification $classification, ?bool $targetActive = null): Classification
    {
        return DB::transaction(function () use ($classification, $targetActive): Classification {
            $this->lockGroupRows($classification->classification_group->value);
            $current = Classification::whereKey($classification->id)->lockForUpdate()->firstOrFail();
            $nextActive = $targetActive ?? ! $current->is_active;

            if ($nextActive && $current->show_in_home_nav) {
                $this->assertWithinHomeNavLimit(
                    $current->classification_group,
                    $current->id,
                    'classification'
                );
            }

            $current->forceFill(['is_active' => $nextActive])->save();

            return $current->refresh();
        });
    }

    public function toggleHomeNav(User $actor, Classification $classification, ?bool $targetShow = null): Classification
    {
        return DB::transaction(function () use ($classification, $targetShow): Classification {
            $this->lockGroupRows($classification->classification_group->value);
            $current = Classification::whereKey($classification->id)->lockForUpdate()->firstOrFail();
            $nextShow = $targetShow ?? ! $current->show_in_home_nav;

            if ($nextShow && $current->is_active) {
                $this->assertWithinHomeNavLimit(
                    $current->classification_group,
                    $current->id,
                    'classification'
                );
            }

            $current->forceFill(['show_in_home_nav' => $nextShow])->save();

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

    private function lockGroupRows(string $groupValue): void
    {
        Classification::query()
            ->where('classification_group', $groupValue)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function assertWithinHomeNavLimit(ClassificationGroup|string $group, ?int $ignoreId, string $errorField): void
    {
        $enum = $group instanceof ClassificationGroup ? $group : ClassificationGroup::from($group);

        $activeSelectedCount = Classification::query()
            ->where('classification_group', $enum->value)
            ->where('is_active', true)
            ->where('show_in_home_nav', true)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->count();

        if ($activeSelectedCount >= self::MAX_HOME_NAV_PER_GROUP) {
            throw ValidationException::withMessages([
                $errorField => sprintf(
                    'Nhóm %s đã đủ tối đa %d phân loại đang hoạt động hiển thị ở menu & trang chủ. Vui lòng bỏ chọn hoặc tạm ngưng một mục khác trước.',
                    $enum->label(),
                    self::MAX_HOME_NAV_PER_GROUP
                ),
            ]);
        }
    }
}
