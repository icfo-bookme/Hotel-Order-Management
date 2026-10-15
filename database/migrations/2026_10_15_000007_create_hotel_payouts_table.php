<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id')->unique();
            $table->foreignId('hotel_booking_id')->constrained('hotel_bookings')->restrictOnDelete();
            $table->unsignedBigInteger('bookme_hotel_id')->index();
            $table->foreignId('hotel_payment_account_id')->constrained('hotel_payment_accounts')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at')->index();
            $table->string('transaction_reference')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('remark')->nullable();
            $table->string('payment_method', 20);
            $table->string('account_name');
            $table->string('account_number', 100);
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('routing_number', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->timestamps();
            $table->index(['hotel_booking_id', 'bookme_hotel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_payouts');
    }
};
