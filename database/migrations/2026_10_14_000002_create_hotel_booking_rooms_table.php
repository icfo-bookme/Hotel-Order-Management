<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained('hotel_bookings')->restrictOnDelete();
            $table->string('hotel_name');
            $table->string('room_name');
            $table->date('check_in_date');
            $table->date('check_out_date');
            // Total amounts for this room's stay, rather than nightly rates.
            $table->decimal('customer_sell', 12, 2);
            $table->decimal('partner_share', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_rooms');
    }
};
