<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Change password: the current one is required and the new one follows the registration rules.
 */
class PasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password', RegisterRequest::passwordFitsHash(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu mới cần ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
        ];
    }
}
