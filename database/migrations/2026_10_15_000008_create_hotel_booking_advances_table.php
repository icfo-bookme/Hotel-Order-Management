<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_booking_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bookme_hotel_id');
            $table->string('hotel_name');
            $table->decimal('advance_amount', 14, 2);
            $table->timestamps();
            $table->unique(['hotel_booking_id', 'bookme_hotel_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_advances');
    }
};
