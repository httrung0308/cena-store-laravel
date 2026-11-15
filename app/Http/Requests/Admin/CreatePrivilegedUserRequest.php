<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleSlug;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin-only form request for creating a Staff or Admin account.
 */
class CreatePrivilegedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->is_active
            && ! $user->trashed()
            && $user->hasRole(RoleSlug::Admin);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => is_string($this->input('full_name')) ? preg_replace('/\s+/u', ' ', trim($this->input('full_name'))) : $this->input('full_name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
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
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'password' => ['required', 'string', 'min:8', 'confirmed', RegisterRequest::passwordFitsHash(...)],
            'role' => ['required', 'string', Rule::in([RoleSlug::Staff->value, RoleSlug::Admin->value])],
        ];
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
            'email.unique' => 'Email này đã được sử dụng trong hệ thống.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            'role.required' => 'Vui lòng chọn vai trò.',
            'role.in' => 'Vai trò chỉ được phép là Nhân viên hoặc Quản trị viên.',
            '*.string' => 'Giá trị không hợp lệ.',
        ];
    }

    /**
     * @return array{full_name: string, email: string, phone: ?string, password: string, role: RoleSlug}
     */
    public function payload(): array
    {
        return [
            'full_name' => $this->validated('full_name'),
            'email' => $this->validated('email'),
            'phone' => $this->validated('phone'),
            'password' => $this->validated('password'),
            'role' => RoleSlug::from($this->validated('role')),
        ];
    }
}
