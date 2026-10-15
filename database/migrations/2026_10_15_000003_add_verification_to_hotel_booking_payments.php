<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hotel_booking_payments', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_booking_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'verified_at', 'verification_note']);
        });
    }
};
