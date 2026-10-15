<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipTicketSale extends Model
{
    use HasFactory;

    // The table associated with the model.
    protected $table = 'ship_ticket_sales';

    // The attributes that are mass assignable.
    protected $fillable = [
        'customer_name',
        'customer_mobile',
        'whatsapp',
        'sales_source',
        'ship_id',
        'journey_date',
        'return_date',
        'ticket_fee',
        'payment_method',
        'received_amount',
        'due_amount',
        'bftn_status',
        'company_id',
        'issued_date',
        'sold_by',
        'nid',
        'email',
        'number_of_ticket',
        'ticket_category',
        'status',
        'date_of_birth',
        'address',
        'remark1',
        'remark2',
        'hotel_status',
    ];

    // The attributes that should be cast.
    protected $casts = [

        'ticket_fee' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'journey_date' => 'date',
        'return_date' => 'date',
        'issued_date' => 'date',
        'date_of_birth' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scope a query to only include pending dues.
    public function scopeWithDue($query)
    {
        return $query->where('due_amount', '>', 0);
    }

    // Scope a query to only include paid tickets.
    public function scopePaid($query)
    {
        return $query->where('due_amount', 0);
    }

    // Check if the ticket sale has due amount.
    public function hasDue()
    {
        return $this->due_amount > 0;
    }

    // Get the total paid amount.
    public function getTotalPaidAttribute()
    {
        return $this->ticket_fee - $this->due_amount;
    }

    public function remark()
    {
        return $this->hasOne(HotelRemark::class, 'ticket_id', 'id');
    }

    public function hotelBookings(): HasMany
    {
        return $this->hasMany(HotelBooking::class, 'sales_id');
    }

    public function scopeWithVerifiedHotelPayments(Builder $query): Builder
    {
        return $query
            ->whereHas('hotelBookings', fn ($booking) => $booking->where('status', 'confirmed')->has('payments'))
            ->whereDoesntHave('hotelBookings', fn ($booking) => $booking->where('status', 'confirmed')
                ->where(fn ($unverified) => $unverified->doesntHave('payments')
                    ->orWhereHas('payments', fn ($payment) => $payment->where('status', '!=', 'verified'))));
    }
}
