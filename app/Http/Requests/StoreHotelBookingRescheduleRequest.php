<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelBookingRescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bookings.reschedule')
            && (!$this->has('payment') || $this->user()->can('payments.create'));
    }

    public function rules(): array
    {
        return [
            'old_check_in_date' => ['required', 'date_format:Y-m-d'],
            'old_check_out_date' => ['required', 'date_format:Y-m-d'],
            'new_check_in_date' => ['required', 'date_format:Y-m-d'],
            'new_check_out_date' => ['required', 'date_format:Y-m-d', 'after:new_check_in_date'],
            'customer_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'partner_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'reason' => ['required', 'string', 'max:5000'],
            'payment' => ['sometimes', 'array:amount,other_fee,payment_method,paid_at,transaction_reference,attachment'],
            'payment.amount' => ['required_with:payment', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'payment.other_fee' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'payment.payment_method' => ['required_with:payment', 'string', 'max:50'],
            'payment.paid_at' => ['required_with:payment', 'date'],
            'payment.transaction_reference' => ['nullable', 'string', 'max:255'],
            'payment.attachment' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
