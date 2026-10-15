<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    // This project runs on a shared database where the table is already managed by another application, so we guard creation with hasTable() to stay safe on both fresh and existing databases.
    public function up(): void
    {
        if (! Schema::hasTable('hotel_login')) {
            Schema::create('hotel_login', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_login');
    }
};
