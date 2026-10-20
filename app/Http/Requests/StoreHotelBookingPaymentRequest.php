<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'hotel_booking_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('hotel_bookings', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'other_fee' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'attachment' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'attachment_path' => ['prohibited'],
            'payment_method' => ['required', 'string', 'max:50'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:5000'],
            // Set this from the authenticated user in the controller.
            'created_by' => ['prohibited'],
            'status' => ['prohibited'],
            'verified_by' => ['prohibited'],
            'verified_at' => ['prohibited'],
            'verification_note' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Payment amount must be at least 0.01.',
            'amount.decimal' => 'Payment amount may have at most two decimal places.',
        ];
    }
}
