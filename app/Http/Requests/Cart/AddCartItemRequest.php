<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('store.limits.variant_quantity')],
            'version' => ['present', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variant_id.*' => 'Vui lòng chọn size.',
            'quantity.*' => 'Số lượng phải từ 1 đến '.config('store.limits.variant_quantity').'.',
            'version.*' => 'Thiếu phiên bản giỏ hàng.',
        ];
    }
}
