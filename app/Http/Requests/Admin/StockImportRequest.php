<?php

namespace App\Http\Requests\Admin;

use App\Services\StockImportService;
use Illuminate\Foundation\Http\FormRequest;

class StockImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return app(StockImportService::class)->rules($this->route('stock_import') === null);
    }
}
