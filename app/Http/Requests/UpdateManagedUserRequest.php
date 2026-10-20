<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('users.update'));
    }
    public function rules(): array
    {
        return ['name' => ['required','string','max:255'], 'email' => ['required','email','lowercase','max:255', Rule::unique('hotel_login', 'email')->ignore($this->route('user'))], 'password' => ['nullable','string','min:8', 'max:72','confirmed'], 'role_id' => ['required','integer','exists:hotel_roles,id'], 'is_active' => ['required','boolean']];
    }
}
