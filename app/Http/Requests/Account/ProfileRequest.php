<?php

namespace App\Http\Requests\Account;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Name and phone only; email, roles and the active flag are never taken from a profile form.
 */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => is_string($this->input('full_name')) ? preg_replace('/\s+/u', ' ', trim($this->input('full_name'))) : $this->input('full_name'),
            'phone' => PhoneNumber::normalize($this->input('phone')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => 'Số điện thoại không hợp lệ.'];
    }

    /**
     * @return array{full_name: string, phone: ?string}
     */
    public function profile(): array
    {
        return ['full_name' => $this->validated('full_name'), 'phone' => $this->validated('phone')];
    }
}
