<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hotel_login', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('hotel_roles')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_login', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('is_active');
        });
    }
};
