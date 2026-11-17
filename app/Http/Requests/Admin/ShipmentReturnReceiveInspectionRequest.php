<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShipmentReturnReceiveInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $minimumNoteLength = $this->routeIs('admin.shipment-returns.receive-and-restock') ? 5 : 20;

        return [
            'unit_id' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', "min:{$minimumNoteLength}", 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['unit_id' => 'đôi cần xử lý', 'note' => 'ghi chú kiểm tra'];
    }
}
