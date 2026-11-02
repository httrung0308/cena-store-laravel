<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

/** Absolute quantity 1..10; removing a line is a separate DELETE, never quantity 0. */
class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('store.limits.variant_quantity')],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.*' => 'Số lượng phải từ 1 đến '.config('store.limits.variant_quantity').'.',
            'version.*' => 'Thiếu phiên bản giỏ hàng.',
        ];
    }
}
