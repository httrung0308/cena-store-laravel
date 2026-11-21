<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        $this->merge([
            'code' => is_string($code) ? mb_strtoupper(trim($code)) : $code,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = (string) $this->input('type');
        $boundCoupon = $this->route('coupon');
        $ignoreId = $boundCoupon instanceof Coupon ? $boundCoupon->id : null;

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/D', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => [
                'required',
                'integer',
                'min:'.($type === CouponType::FreeShipping->value ? '0' : '1'),
                'max:'.($type === CouponType::Percentage->value ? '100' : ($type === CouponType::FreeShipping->value ? '0' : '1000000000')),
            ],
            'min_order_amount' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'max_discount_amount' => ['exclude_unless:type,PERCENTAGE', 'nullable', 'integer', 'min:1', 'max:1000000000'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'value.required' => 'Vui lòng nhập mức giảm giá.',
            'value.min' => 'Mức giảm giá phải lớn hơn 0 (riêng miễn phí vận chuyển dùng 0).',
            'min_order_amount.required' => 'Vui lòng nhập giá trị đơn hàng tối thiểu; nhập 0 để áp dụng cho mọi đơn.',
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);
        if ($key !== null || ! is_array($data)) {
            return $data;
        }

        $data['max_discount_amount'] ??= null;
        $data['usage_limit'] ??= null;
        $data['starts_at'] ??= null;
        $data['expires_at'] ??= null;
        $data['is_active'] = $this->boolean('is_active');

        return $data;
    }
}
