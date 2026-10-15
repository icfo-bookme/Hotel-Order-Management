<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingAccount extends Model
{
    protected $fillable = ['bookme_hotel_id', 'hotel_payment_account_id'];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(HotelPaymentAccount::class, 'hotel_payment_account_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }
}
