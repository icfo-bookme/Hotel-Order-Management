<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active
            && $this->user()->role?->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'invoice_title' => ['nullable', 'string', 'max:255'],
            'show_invoice_title' => ['sometimes', 'required', 'boolean'],
            'logo' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['sometimes', 'required', 'boolean'],
            'show_logo' => ['sometimes', 'required', 'boolean'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:5000'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'show_company_details' => ['sometimes', 'required', 'boolean'],
            'footer_text' => ['nullable', 'string', 'max:5000'],
            'show_footer' => ['sometimes', 'required', 'boolean'],
            'terms_and_conditions' => ['nullable', 'string', 'max:10000'],
            'show_terms' => ['sometimes', 'required', 'boolean'],
            'logo_path' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.image' => 'The invoice logo must be an image.',
            'logo.mimes' => 'The invoice logo must be a JPG, PNG or WebP image.',
            'logo.max' => 'The invoice logo must not exceed 5 MB.',
        ];
    }
}

