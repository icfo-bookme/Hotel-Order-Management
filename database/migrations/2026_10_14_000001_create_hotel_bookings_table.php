<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('ship_ticket_sales')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bookings');
    }
};
