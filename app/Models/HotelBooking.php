<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_id',
        'status',
        'remark',
        'created_by',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(ShipTicketSale::class, 'sales_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HotelBookingRoom::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(HotelBookingAccount::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(HotelBookingAdvance::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(HotelPayout::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(HotelBookingPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
