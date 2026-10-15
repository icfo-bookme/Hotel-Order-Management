<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
