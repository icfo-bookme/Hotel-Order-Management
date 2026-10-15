<?php

namespace App\Http\Requests;

class UpdateHotelBookingPaymentRequest extends StoreHotelBookingPaymentRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $field => &$fieldRules) {
            if ($field !== 'created_by' && !in_array('sometimes', $fieldRules, true)) {
                array_unshift($fieldRules, 'sometimes');
            }
        }
        unset($fieldRules);

        // Preserve the original booking and payment creator.
        $rules['hotel_booking_id'] = ['prohibited'];

        return $rules;
    }
}
