<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelRemark extends Model
{
    use HasFactory;

    protected $table = 'hotel_remarks';

    protected $fillable = [
        'ticket_id',
        'remark_text',
        'added_by',
    ];


    public function ticket()
    {
        return $this->belongsTo(ShipTicketSale::class, 'ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'added_by', 'id');
    }
}
