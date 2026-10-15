<?php

namespace App\Http\Requests;

class UpdateHotelBookingRoomRequest extends StoreHotelBookingRoomRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // Update only submitted fields; do not move rooms between bookings.
        foreach ($rules as &$fieldRules) {
            if (! in_array('prohibited', $fieldRules, true)) {
                array_unshift($fieldRules, 'sometimes');
            }
        }
        unset($fieldRules);
        $rules['hotel_booking_id'] = ['prohibited'];

        // Submit both dates together so the stay can always be validated.
        $rules['check_in_date'] = ['required_with:check_out_date', 'date_format:Y-m-d'];
        $rules['check_out_date'] = ['required_with:check_in_date', 'date_format:Y-m-d', 'after:check_in_date'];

        return $rules;
    }
}
