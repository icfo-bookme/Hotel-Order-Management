<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelPaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'bookme_hotel_id' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in(['bank', 'bkash', 'nagad'])],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'bank_name' => ['required_if:payment_method,bank', 'nullable', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'created_by' => ['prohibited'],
            'hotel_name' => ['prohibited'],
        ];
    }
}
