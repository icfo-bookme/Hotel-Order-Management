<?php

namespace App\Http\Requests;

class UpdateHotelRemarkRequest extends StoreHotelRemarkRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'ticket_id' => ['required', 'integer', 'exists:hotel_remarks,ticket_id'],
        ]);
    }
}
