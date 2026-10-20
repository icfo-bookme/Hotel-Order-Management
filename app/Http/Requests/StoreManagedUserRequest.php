<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('users.create') && $this->user()?->can('users.assign-role'));
    }
    public function rules(): array
    {
        return ['name' => ['required','string','max:255'], 'email' => ['required','email','lowercase','max:255', Rule::unique('hotel_login', 'email')], 'password' => ['required','string','min:8', 'max:72','confirmed'], 'role_id' => ['required','integer','exists:hotel_roles,id'], 'is_active' => ['required','boolean']];
    }
}
