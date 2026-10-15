<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_booking_id',
        'hotel_name',
        'bookme_hotel_id',
        'room_name',
        'check_in_date',
        'check_out_date',
        'customer_price_per_night',
        'partner_price_per_night',
    ];

    protected $appends = ['nights', 'customer_sell', 'partner_share'];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'customer_price_per_night' => 'decimal:2',
            'partner_price_per_night' => 'decimal:2',
        ];
    }

    protected function nights(): Attribute
    {
        return Attribute::get(fn () => $this->check_in_date && $this->check_out_date
            ? max(0, (int) $this->check_in_date->diffInDays($this->check_out_date, false)) : 0);
    }

    protected function customerSell(): Attribute
    {
        return Attribute::get(fn () => $this->stayTotal($this->customer_price_per_night ?? '0.00'));
    }

    protected function partnerShare(): Attribute
    {
        return Attribute::get(fn () => $this->stayTotal($this->partner_price_per_night ?? '0.00'));
    }

    private function stayTotal(string $rate): string
    {
        return Money::format(Money::cents($rate) * $this->nights);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }
}
