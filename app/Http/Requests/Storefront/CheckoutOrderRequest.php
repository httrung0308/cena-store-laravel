<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Place an order. Prices, totals and the owner never come from the form; the fingerprint only proves the
 * customer saw the quote that the server recomputes under lock.
 */
class CheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'min:1'],
            'checkout_key' => ['required', 'uuid'],
            'fingerprint' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/D'],
            'payment_method' => ['required', Rule::in(['COD', 'PREPAID_DEMO'])],
            'coupon_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/D'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['address_id' => 'địa chỉ giao hàng', 'payment_method' => 'phương thức thanh toán', 'coupon_code' => 'mã coupon', 'customer_note' => 'ghi chú'];
    }
}
