<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelPaymentAccount extends Model
{
    protected $fillable = ['bookme_hotel_id', 'hotel_name', 'payment_method', 'account_name', 'account_number', 'bank_name', 'branch_name', 'routing_number', 'is_default', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
