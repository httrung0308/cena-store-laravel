<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleSlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The full set of roles an Admin assigns to a user.
 */
class UserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1', 'max:3'],
            'roles.*' => ['string', 'distinct', Rule::enum(RoleSlug::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['roles.required' => 'Chọn ít nhất một vai trò.', 'roles.min' => 'Chọn ít nhất một vai trò.'];
    }

    /** @return list<RoleSlug> */
    public function roles(): array
    {
        return array_values(array_map(fn (string $role): RoleSlug => RoleSlug::from($role), $this->validated('roles')));
    }
}
