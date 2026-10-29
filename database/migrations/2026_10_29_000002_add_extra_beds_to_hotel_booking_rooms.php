<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_booking_rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('extra_bed_count')->default(0);
            $table->decimal('extra_bed_customer_rate', 12, 2)->default(0);
            $table->decimal('extra_bed_partner_rate', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_booking_rooms', fn (Blueprint $table) => $table->dropColumn([
            'extra_bed_count', 'extra_bed_customer_rate', 'extra_bed_partner_rate',
        ]));
    }
};
