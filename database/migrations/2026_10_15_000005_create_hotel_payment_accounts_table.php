<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bookme_hotel_id')->index();
            $table->string('hotel_name');
            $table->string('payment_method', 20);
            $table->string('account_name');
            $table->string('account_number', 100);
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('routing_number', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_payment_accounts');
    }
};
