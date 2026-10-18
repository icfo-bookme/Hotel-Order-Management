<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingReschedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_booking_room_id',
        'old_check_in_date',
        'old_check_out_date',
        'new_check_in_date',
        'new_check_out_date',
        'customer_fee',
        'partner_fee',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'old_check_in_date' => 'date',
            'old_check_out_date' => 'date',
            'new_check_in_date' => 'date',
            'new_check_out_date' => 'date',
            'customer_fee' => 'decimal:2',
            'partner_fee' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelBookingRoom::class, 'hotel_booking_room_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
