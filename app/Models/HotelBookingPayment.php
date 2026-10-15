<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_booking_id',
        'amount',
        'other_fee',
        'attachment_path',
        'payment_method',
        'transaction_reference',
        'paid_at',
        'remark',
        'created_by',
    ];

    protected $attributes = ['other_fee' => '0.00', 'status' => 'pending'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'other_fee' => 'decimal:2',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
