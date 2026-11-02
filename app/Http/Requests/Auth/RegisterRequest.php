<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public sign-up. Only these four fields are accepted; roles and account flags are always set by the server.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => is_string($this->input('full_name')) ? preg_replace('/\s+/u', ' ', trim($this->input('full_name'))) : $this->input('full_name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'phone' => is_string($this->input('phone')) && trim($this->input('phone')) !== '' ? preg_replace('/[\s.-]/', '', $this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'regex:/^(0|\+84)[0-9]{9,10}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed', self::passwordFitsHash(...)],
        ];
    }

    /**
     * Bcrypt only uses the first 72 bytes; longer passwords are rejected instead of silently truncated.
     */
    public static function passwordFitsHash(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > 72) {
            $fail('Mật khẩu tối đa 72 byte (khoảng 72 ký tự không dấu).');
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'full_name.min' => 'Họ và tên cần ít nhất 2 ký tự.',
            'full_name.max' => 'Họ và tên tối đa 100 ký tự.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.max' => 'Email quá dài.',
            'email.unique' => 'Email này đã được đăng ký.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            '*.string' => 'Giá trị không hợp lệ.',
        ];
    }

    /**
     * @return array{full_name: string, email: string, phone: ?string, password: string}
     */
    public function registration(): array
    {
        return [
            'full_name' => $this->validated('full_name'),
            'email' => $this->validated('email'),
            'phone' => $this->validated('phone'),
            'password' => $this->validated('password'),
        ];
    }
}
