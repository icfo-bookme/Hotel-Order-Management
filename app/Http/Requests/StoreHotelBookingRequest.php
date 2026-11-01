<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sales_id' => ['required', 'integer', 'exists:ship_ticket_sales,id'],
            'status' => ['sometimes', 'required', Rule::in(['pending', 'confirmed', 'cancelled'])],
            'reservation_number' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:5000'],
            // Set this from the authenticated user in the controller.
            'created_by' => ['prohibited'],
        ];
    }
}
