<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_settings', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_title')->nullable();
            $table->boolean('show_invoice_title')->default(true);
            $table->string('logo_path')->nullable();
            $table->boolean('show_logo')->default(true);
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_phone', 50)->nullable();
            $table->string('company_email')->nullable();
            $table->boolean('show_company_details')->default(true);
            $table->text('footer_text')->nullable();
            $table->boolean('show_footer')->default(true);
            $table->text('terms_and_conditions')->nullable();
            $table->boolean('show_terms')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_settings');
    }
};
