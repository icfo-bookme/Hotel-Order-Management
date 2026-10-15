<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelAdvancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'hotel_advances' => ['required', 'array', 'min:1', 'max:50'],
            'hotel_advances.*' => ['array:bookme_hotel_id,amount'],
            'hotel_advances.*.bookme_hotel_id' => ['required', 'integer', 'min:1', 'distinct'],
            'hotel_advances.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
        ];
    }
}
