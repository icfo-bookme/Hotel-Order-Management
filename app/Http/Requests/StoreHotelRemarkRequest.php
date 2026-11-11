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
            'is_follow_up' => ['sometimes', 'boolean'],
            'follow_up_date' => ['exclude_unless:is_follow_up,1', 'required', 'date_format:Y-m-d'],
        ];
    }
}
