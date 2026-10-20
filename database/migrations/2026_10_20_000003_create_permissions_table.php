<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hotel_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('group', 100)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_permissions');
    }
};
