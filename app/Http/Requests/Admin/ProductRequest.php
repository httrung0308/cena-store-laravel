<?php

namespace App\Http\Requests\Admin;

use App\Services\ProductManagementService;
use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Multipart file bags may merge numeric indexes in a different insertion order. */
    public function validationData(): array
    {
        return app(ProductManagementService::class)->orderedInput(parent::validationData());
    }

    public function rules(): array
    {
        return app(ProductManagementService::class)->rules($this->route('product'));
    }
}
