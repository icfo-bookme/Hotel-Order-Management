<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveHotelBookingRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bookings.update')
            && ((float) $this->input('refund_amount', 0) <= 0 || $this->user()->can('payments.create'))
            && ((float) $this->input('hotel_refund_amount', 0) <= 0 || $this->user()->can('payouts.create'));
    }

    public function rules(): array
    {
        return [
            'refund_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'hotel_refund_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'payment_method' => ['required', 'in:cash,bank,bkash,nagad,card,other'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:1000'],
            'confirmed' => ['accepted'],
        ];
    }
}
