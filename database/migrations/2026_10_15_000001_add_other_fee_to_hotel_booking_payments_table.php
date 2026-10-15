<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hotel_booking_payments', function (Blueprint $table) {
            $table->decimal('other_fee', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_booking_payments', function (Blueprint $table) {
            $table->dropColumn('other_fee');
        });
    }
};
