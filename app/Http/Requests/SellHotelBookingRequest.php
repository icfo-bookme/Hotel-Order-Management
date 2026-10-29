<?php

namespace App\Http\Requests;

class SellHotelBookingRequest extends StoreHotelBookingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bookings.sell')
            && (!$this->filled('payments') || $this->user()->can('payments.create'));
    }

    public function rules(): array
    {
        $rules = [
            'remark' => ['nullable', 'string', 'max:5000'],
            'rooms' => ['required', 'array', 'min:1', 'max:50'],
            'rooms.*' => ['required', 'array:hotel_name,room_name,check_in_date,check_out_date,customer_price_per_night,partner_price_per_night,bookme_hotel_id,extra_bed_count,extra_bed_customer_rate,extra_bed_partner_rate'],
            'rooms.*.bookme_hotel_id' => ['required', 'integer', 'min:1'],
            'payments' => ['sometimes', 'array', 'max:50'],
            'payments.*' => ['required', 'array:amount,other_fee,payment_method,transaction_reference,paid_at,remark,attachment'],
            'created_by' => ['prohibited'],
            'hotel_advances' => ['required', 'array', 'min:1', 'max:50'],
            'hotel_advances.*' => ['array:bookme_hotel_id,amount'],
            'hotel_advances.*.bookme_hotel_id' => ['required', 'integer', 'min:1', 'distinct'],
            'hotel_advances.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
        ];

        foreach ((new StoreHotelBookingRoomRequest())->rules() as $field => $fieldRules) {
            if ($field === 'hotel_booking_id') {
                continue;
            }
            $rules['rooms.*.'.$field] = array_map(
                fn ($rule) => $rule === 'after:check_in_date' ? 'after:rooms.*.check_in_date' : $rule,
                $fieldRules,
            );
        }
        foreach ((new StoreHotelBookingPaymentRequest())->rules() as $field => $fieldRules) {
            if ($field !== 'hotel_booking_id') {
                $rules['payments.*.'.$field] = $fieldRules;
            }
        }

        return $rules;
    }

}
