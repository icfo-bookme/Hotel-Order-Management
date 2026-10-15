<?php

namespace App\Http\Requests;

class UpdateHotelPaymentAccountRequest extends StoreHotelPaymentAccountRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['bookme_hotel_id'] = ['prohibited'];

        return $rules;
    }
}
