<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hotel_booking_rooms', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('removed_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->text('removal_reason')->nullable();
        });
        foreach (['hotel_booking_payments', 'hotel_payouts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('refunded_room_id')->nullable()->unique()->constrained('hotel_booking_rooms')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['hotel_booking_payments', 'hotel_payouts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['refunded_room_id']);
                $table->dropConstrainedForeignId('refunded_room_id');
            });
        }
        Schema::table('hotel_booking_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('removed_by');
            $table->dropColumn(['deleted_at', 'removal_reason']);
        });
    }
};
