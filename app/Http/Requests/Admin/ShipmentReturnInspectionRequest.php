<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShipmentReturnInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:20', 'max:500']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['note' => 'ghi chú tình trạng/chứng cứ'];
    }
}
