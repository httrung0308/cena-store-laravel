<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class AdministrativeUnitResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'level' => $this->level?->value,
            'parent_id' => $this->parent_id,
            'dataset_version' => $this->dataset_version,
            'valid_from' => $this->valid_from?->toISOString(),
            'valid_to' => $this->valid_to?->toISOString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
