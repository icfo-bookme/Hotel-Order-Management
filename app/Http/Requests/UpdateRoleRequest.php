<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('roles.update'));
    }
    public function rules(): array
    {
        return ['name' => ['required','string','max:100', Rule::notIn([\App\Models\Role::SUPER_ADMIN]), Rule::unique('hotel_roles', 'name')->ignore($this->route('role'))], 'permissions' => ['present','array','max:200'], 'permissions.*' => ['integer','distinct','exists:hotel_permissions,id']];
    }
}
