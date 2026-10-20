<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('hotel_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('hotel_permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_role_permissions');
    }
};
