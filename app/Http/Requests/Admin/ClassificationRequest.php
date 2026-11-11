<?php

namespace App\Http\Requests\Admin;

use App\Services\ClassificationManagementService;
use Illuminate\Foundation\Http\FormRequest;

class ClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return app(ClassificationManagementService::class)->rules(
            $this->route('classification'),
            $this->input('classification_group')
        );
    }
}
