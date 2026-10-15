<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelBookingRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'hotel_booking_id' => ['required', 'integer', 'exists:hotel_bookings,id'],
            'hotel_name' => ['required', 'string', 'max:255'],
            'room_name' => ['required', 'string', 'max:255'],
            'check_in_date' => ['required', 'date_format:Y-m-d'],
            'check_out_date' => ['required', 'date_format:Y-m-d', 'after:check_in_date'],
            'customer_price_per_night' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'partner_price_per_night' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'customer_sell' => ['prohibited'],
            'partner_share' => ['prohibited'],
            'nights' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'check_out_date.after' => 'Check-out date must be after the check-in date.',
            'customer_price_per_night.decimal' => 'Customer nightly rate may have at most two decimal places.',
            'partner_price_per_night.decimal' => 'Partner nightly rate may have at most two decimal places.',
        ];
    }
}
