<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HotelReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_from' => $this->input('date_from', now('Asia/Dhaka')->startOfMonth()->toDateString()),
            'date_to' => $this->input('date_to', now('Asia/Dhaka')->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotel_booking_rooms', 'bookme_hotel_id')],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
