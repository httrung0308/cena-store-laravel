<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class AddressResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'province_id' => $this->province_id,
            'ward_id' => $this->ward_id,
            'province_code_snapshot' => $this->province_code_snapshot,
            'province_name_snapshot' => $this->province_name_snapshot,
            'ward_code_snapshot' => $this->ward_code_snapshot,
            'ward_name_snapshot' => $this->ward_name_snapshot,
            'dataset_version' => $this->dataset_version,
            'street_address' => $this->street_address,
            'legacy_district_text' => $this->legacy_district_text,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
