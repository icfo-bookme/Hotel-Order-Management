<?php

namespace App\Http\Requests;

class EditHotelSaleRequest extends SellHotelBookingRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('payments') && in_array($this->input('payments'), [null, ''], true)) {
            $this->merge(['payments' => []]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('bookings.update') ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['version'] = ['required', 'string', 'size:64'];
        $rules['rooms.*'][1] .= ',id';
        $rules['rooms.*.id'] = ['nullable', 'integer', 'min:1', 'distinct'];
        $rules['payments'] = ['present', 'array', 'max:50'];
        $rules['payments.*'][1] .= ',id,remove_attachment';
        $rules['payments.*.id'] = ['nullable', 'integer', 'min:1', 'distinct'];
        // Existing refund entries must be returned unchanged; the service enforces this.
        $rules['payments.*.amount'] = ['required', 'numeric', 'decimal:0,2', 'min:-9999999999.99', 'max:9999999999.99'];
        $rules['payments.*.remove_attachment'] = ['sometimes', 'boolean'];
        return $rules;
    }
}
