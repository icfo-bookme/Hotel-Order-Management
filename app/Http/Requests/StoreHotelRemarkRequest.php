<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelRemarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer', 'exists:ship_ticket_sales,id'],
            'remark_text' => ['required', 'string', 'max:1000'],
        ];
    }
}
