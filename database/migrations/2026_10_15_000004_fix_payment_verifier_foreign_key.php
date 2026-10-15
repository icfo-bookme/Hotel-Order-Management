<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hotel_booking_payments', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->foreign('verified_by')->references('id')->on('hotel_login')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_booking_payments', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
