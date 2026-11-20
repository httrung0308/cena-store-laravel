<?php

namespace App\Http\Requests\Admin;

use App\Services\ProductManagementTaxonomyService;
use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $normalizedCode = is_string($code) ? mb_strtoupper(trim($code)) : null;

        $this->merge([
            'code' => $normalizedCode === '' ? null : $normalizedCode,
            'is_active' => $this->input('is_active', '0'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return app(ProductManagementTaxonomyService::class)->supplierRules($this->route('supplier'));
    }
}
