<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'in:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            '*.string' => 'Giá trị không hợp lệ.',
            '*.max' => 'Giá trị quá dài.',
        ];
    }

    /**
     * Rate-limit key: normalised email + client IP, so one attacker cannot lock every account
     * and one account is not unlocked by changing IPs alone.
     */
    public function throttleKey(): string
    {
        return 'login:'.Str::transliterate((string) $this->input('email')).'|'.$this->ip();
    }
}
