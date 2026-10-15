<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListHotelBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sales_id' => ['sometimes', 'required', 'integer', 'exists:ship_ticket_sales,id'],
            'status' => ['sometimes', 'required', 'in:pending,confirmed,cancelled'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
