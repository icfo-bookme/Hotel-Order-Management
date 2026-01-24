<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipTicketSale extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ship_ticket_sales';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
        'hotel_status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [

        'ticket_fee' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [

        'created_at',
        'updated_at',
    ];

    /**
     * Scope a query to only include pending dues.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDue($query)
    {
        return $query->where('due_amount', '>', 0);
    }

    /**
     * Scope a query to only include paid tickets.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('due_amount', 0);
    }

    /**
     * Check if the ticket sale has due amount.
     *
     * @return bool
     */
    public function hasDue()
    {
        return $this->due_amount > 0;
    }

    /**
     * Get the total paid amount.
     *
     * @return float
     */
    public function getTotalPaidAttribute()
    {
        return $this->ticket_fee - $this->due_amount;
    }

    public function remark()
    {
        return $this->hasOne(HotelRemark::class, 'ticket_id', 'id');
    }

    
}
