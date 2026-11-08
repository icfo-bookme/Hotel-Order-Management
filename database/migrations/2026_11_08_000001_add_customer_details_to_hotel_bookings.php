<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_id')->nullable()->change();
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_mobile', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
        });
        DB::table('hotel_bookings')->orderBy('id')->chunkById(500, function ($bookings) {
            $sales = DB::table('ship_ticket_sales')->whereIn('id', $bookings->pluck('sales_id'))->get()->keyBy('id');
            foreach ($bookings as $booking) {
                if ($sale = $sales->get($booking->sales_id)) {
                    DB::table('hotel_bookings')->where('id', $booking->id)->update([
                        'customer_name' => $sale->customer_name,
                        'customer_mobile' => $sale->customer_mobile,
                        'whatsapp' => $sale->whatsapp,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (DB::table('hotel_bookings')->whereNull('sales_id')->exists()) {
            throw new RuntimeException('Direct bookings exist. Export and resolve them before rolling back.');
        }
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_mobile', 'whatsapp']);
            $table->unsignedBigInteger('sales_id')->nullable(false)->change();
        });
    }
};
