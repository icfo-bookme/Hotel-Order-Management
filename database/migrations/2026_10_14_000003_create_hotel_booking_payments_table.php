<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained('hotel_bookings')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50);
            $table->string('transaction_reference')->nullable();
            $table->dateTime('paid_at')->index();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_payments');
    }
};
