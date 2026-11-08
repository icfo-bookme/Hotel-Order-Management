<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelBooking extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'sales_id',
        'customer_name',
        'customer_mobile',
        'whatsapp',
        'status',
        'remark',
        'reservation_number',
        'created_by',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function getCustomerNameAttribute($value): ?string
    {
        return $value ?? $this->sale?->customer_name;
    }

    public function getCustomerMobileAttribute($value): ?string
    {
        return $value ?? $this->sale?->customer_mobile;
    }

    public function getWhatsappAttribute($value): ?string
    {
        return ($this->attributes['customer_name'] ?? null) !== null ? $value : ($value ?? $this->sale?->whatsapp);
    }

    public function scopeSearchCustomer($query, string $keyword)
    {
        return $query->where(function ($query) use ($keyword) {
            foreach (['customer_name', 'customer_mobile', 'whatsapp'] as $field) {
                $query->orWhere('hotel_bookings.'.$field, 'like', '%'.$keyword.'%');
            }
            $query->orWhereHas('sale', fn ($sale) => $sale->where('customer_name', 'like', '%'.$keyword.'%')
                ->orWhere('customer_mobile', 'like', '%'.$keyword.'%')->orWhere('whatsapp', 'like', '%'.$keyword.'%'));
        });
    }

    public function scopeWithVerifiedPayments($query)
    {
        return $query->where(function ($query) {
            $query->whereHas('sale', fn ($sale) => $sale->where('hotel_status', 'sold')->withVerifiedHotelPayments())
                ->orWhere(fn ($direct) => $direct->whereNull('sales_id')->has('payments')
                    ->whereDoesntHave('payments', fn ($payment) => $payment->where('status', '!=', 'verified')));
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(ShipTicketSale::class, 'sales_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HotelBookingRoom::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(HotelBookingAdvance::class);
    }

    public function removedRooms(): HasMany
    {
        return $this->hasMany(HotelBookingRoom::class)->onlyTrashed();
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

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
