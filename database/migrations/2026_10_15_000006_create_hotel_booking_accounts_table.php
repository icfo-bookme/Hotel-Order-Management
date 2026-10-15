<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hotel_booking_rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('bookme_hotel_id')->nullable()->index();
        });
        Schema::create('hotel_booking_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bookme_hotel_id');
            $table->foreignId('hotel_payment_account_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['hotel_booking_id', 'bookme_hotel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_accounts');
        Schema::table('hotel_booking_rooms', function (Blueprint $table) {
            $table->dropIndex(['bookme_hotel_id']);
            $table->dropColumn('bookme_hotel_id');
        });
    }
};
