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
        $this->merge(['is_active' => $this->input('is_active', '0')]);
    }

    public function rules(): array
    {
        return app(ProductManagementTaxonomyService::class)->supplierRules($this->route('supplier'));
    }
}
