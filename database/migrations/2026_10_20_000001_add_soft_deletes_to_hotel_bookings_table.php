<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('hotel_login')->nullOnDelete();
            $table->text('delete_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('delete_reason');
            $table->dropSoftDeletes();
        });
    }
};
