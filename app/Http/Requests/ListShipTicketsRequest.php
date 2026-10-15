<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListShipTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function validationData(): array
    {
        return array_merge(parent::validationData(), ['status' => strtolower((string) $this->route('status'))]);
    }

    public function rules(): array
    {
        return ['status' => ['required', 'in:pending,feedbacked,pending-sold,sold,booked']];
    }
}
