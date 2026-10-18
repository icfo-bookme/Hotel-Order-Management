<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_booking_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_room_id')->constrained('hotel_booking_rooms')->restrictOnDelete();
            $table->date('old_check_in_date');
            $table->date('old_check_out_date');
            $table->date('new_check_in_date');
            $table->date('new_check_out_date');
            $table->decimal('customer_fee', 12, 2)->default(0);
            $table->decimal('partner_fee', 12, 2)->default(0);
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_reschedules');
    }
};
