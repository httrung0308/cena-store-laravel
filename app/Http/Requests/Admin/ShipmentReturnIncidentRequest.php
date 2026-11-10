<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShipmentReturnIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', 'min:1'],
            'incident_type' => ['required', 'string', 'in:MISSING_UNIT,SKU_MISMATCH,DAMAGED_UNIT,NO_EVIDENCE'],
            'note' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'unit_id' => 'đôi cần ghi nhận',
            'incident_type' => 'loại bất thường',
            'note' => 'ghi chú/chứng cứ',
        ];
    }
}
