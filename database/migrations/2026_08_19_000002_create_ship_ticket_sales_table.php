<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    // Mirrors the schema of the existing ship_ticket_sales table on the shared database. Guarded with hasTable() so it is safe on fresh installs and on the production database where the table already exists.
    public function up(): void
    {
        if (! Schema::hasTable('ship_ticket_sales')) {
            Schema::create('ship_ticket_sales', function (Blueprint $table) {
                $table->id();
                $table->string('customer_name', 100);
                $table->string('customer_mobile', 20)->index();
                $table->string('sales_source')->nullable();
                $table->unsignedBigInteger('ship_id')->index();
                $table->date('journey_date')->nullable()->index();
                $table->decimal('ticket_fee', 10, 2);
                $table->string('payment_method')->nullable();
                $table->decimal('received_amount', 10, 2);
                $table->decimal('due_amount', 10, 2)->default(0.00);
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->date('issued_date');
                $table->integer('sold_by')->nullable();
                $table->string('status', 100)->default('pending')->index();
                $table->string('nid')->nullable();
                $table->string('email')->nullable();
                $table->integer('number_of_ticket')->nullable();
                $table->date('return_date')->nullable();
                $table->string('ticket_category')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('address', 500)->nullable();
                $table->string('whatsapp', 20)->nullable();
                $table->text('remark1')->nullable();
                $table->text('remark2')->nullable();
                $table->string('bftn_status', 50)->nullable();
                $table->string('hotel_status', 255)->default('pending');
                $table->string('pdf_status', 20)->default('pending');
                $table->integer('other_fee')->default(0);
                $table->integer('total_payable')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ship_ticket_sales');
    }
};
