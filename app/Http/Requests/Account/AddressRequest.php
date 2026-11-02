<?php

namespace App\Http\Requests\Account;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Address form. The owner always comes from the session; province/ward are checked against the
 * current snapshot by AddressService, which also stores the name/code/version snapshot.
 */
class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipient_name' => is_string($this->input('recipient_name')) ? preg_replace('/\s+/u', ' ', trim($this->input('recipient_name'))) : $this->input('recipient_name'),
            'phone' => PhoneNumber::normalize($this->input('phone')),
            'street_address' => is_string($this->input('street_address')) ? preg_replace('/\s+/u', ' ', trim($this->input('street_address'))) : $this->input('street_address'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN],
            'province_code' => ['required', 'string', 'regex:/\A[0-9]{2}\z/'],
            'ward_code' => ['required', 'string', 'regex:/\A[0-9]{5}\z/'],
            'street_address' => ['required', 'string', 'min:3', 'max:255'],
            'is_default' => ['nullable', 'array'],
            'is_default.*' => ['in:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_name.required' => 'Vui lòng nhập tên người nhận.',
            'recipient_name.min' => 'Tên người nhận cần ít nhất 2 ký tự.',
            'recipient_name.max' => 'Tên người nhận tối đa 100 ký tự.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'province_code.required' => 'Vui lòng chọn tỉnh / thành phố.',
            'province_code.regex' => 'Tỉnh / thành phố không hợp lệ.',
            'ward_code.required' => 'Vui lòng chọn phường / xã / đặc khu.',
            'ward_code.regex' => 'Phường / xã không hợp lệ.',
            'street_address.required' => 'Vui lòng nhập số nhà, tên đường.',
            'street_address.min' => 'Địa chỉ quá ngắn.',
            'street_address.max' => 'Địa chỉ tối đa 255 ký tự.',
            '*.string' => 'Giá trị không hợp lệ.',
        ];
    }

    /**
     * @return array{recipient_name: string, phone: string, province_code: string, ward_code: string, street_address: string, is_default: bool}
     */
    public function address(): array
    {
        return [
            'recipient_name' => $this->validated('recipient_name'),
            'phone' => $this->validated('phone'),
            'province_code' => $this->validated('province_code'),
            'ward_code' => $this->validated('ward_code'),
            'street_address' => $this->validated('street_address'),
            'is_default' => in_array('1', (array) $this->validated('is_default', []), true),
        ];
    }
}
