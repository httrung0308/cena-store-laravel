<?php

namespace App\Http\Requests\Admin;

use App\Enums\OperationsWorkItemStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationsWorkItemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in([OperationsWorkItemStatus::Pending->value, OperationsWorkItemStatus::InProgress->value])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
