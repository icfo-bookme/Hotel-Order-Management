<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    // Mirrors the schema of the existing hotel_remarks table on the shared database. Guarded with hasTable() so it is safe on fresh installs and on the production database where the table already exists.
    public function up(): void
    {
        if (! Schema::hasTable('hotel_remarks')) {
            Schema::create('hotel_remarks', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('ticket_id')->index();
                $table->text('remark_text');
                $table->unsignedBigInteger('added_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_remarks');
    }
};
