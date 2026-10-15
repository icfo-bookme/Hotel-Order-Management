<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingAdvance extends Model
{
    protected $fillable = ['bookme_hotel_id', 'hotel_name', 'advance_amount'];

    protected function casts(): array
    {
        return ['advance_amount' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }
}
