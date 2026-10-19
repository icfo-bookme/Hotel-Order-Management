<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'hotel_booking_id',
        'bookme_hotel_id',
        'hotel_payment_account_id',
        'amount',
        'paid_at',
        'transaction_reference',
        'attachment_path',
        'remark',
        'payment_method',
        'account_name',
        'account_number',
        'bank_name',
        'branch_name',
        'routing_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(HotelPaymentAccount::class, 'hotel_payment_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function paymentAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HotelPaymentAccount::class, 'bookme_hotel_id', 'bookme_hotel_id')->orderBy('id');
    }
}
