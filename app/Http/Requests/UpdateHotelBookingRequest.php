<?php

namespace App\Http\Requests;

class UpdateHotelBookingRequest extends StoreHotelBookingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // A booking stays attached to its original ticket sale.
        $rules['sales_id'] = ['prohibited'];

        return $rules;
    }
}
