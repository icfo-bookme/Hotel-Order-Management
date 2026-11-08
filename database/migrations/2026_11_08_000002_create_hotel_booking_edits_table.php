<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hotel_booking_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_edits');
    }
};
