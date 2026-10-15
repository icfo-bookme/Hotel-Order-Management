<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Verify conversion before altering columns; never round existing money.
        foreach (DB::table('hotel_booking_rooms')->cursor() as $room) {
            $nights = $this->nights($room);
            if ($nights < 1 || $this->cents($room->customer_sell) % $nights !== 0 || $this->cents($room->partner_share) % $nights !== 0) {
                throw new RuntimeException('Room '.$room->id.' cannot be converted to two-decimal nightly rates without changing its totals. Resolve its rates before migrating.');
            }
        }

        Schema::table('hotel_booking_rooms', function (Blueprint $table) {
            $table->renameColumn('customer_sell', 'customer_price_per_night');
            $table->renameColumn('partner_share', 'partner_price_per_night');
        });

        DB::table('hotel_booking_rooms')->orderBy('id')->chunkById(100, function ($rooms) {
            foreach ($rooms as $room) {
                $nights = $this->nights($room);
                DB::table('hotel_booking_rooms')->where('id', $room->id)->update([
                    'customer_price_per_night' => $this->money(intdiv($this->cents($room->customer_price_per_night), $nights)),
                    'partner_price_per_night' => $this->money(intdiv($this->cents($room->partner_price_per_night), $nights)),
                ]);
            }
        });
    }

    public function down(): void
    {
        foreach (DB::table('hotel_booking_rooms')->cursor() as $room) {
            if ($this->cents($room->customer_price_per_night) * $this->nights($room) > 999999999999 || $this->cents($room->partner_price_per_night) * $this->nights($room) > 999999999999) {
                throw new RuntimeException('Room '.$room->id.' exceeds the old total column capacity; rollback would lose money.');
            }
        }
        DB::table('hotel_booking_rooms')->orderBy('id')->chunkById(100, function ($rooms) {
            foreach ($rooms as $room) {
                DB::table('hotel_booking_rooms')->where('id', $room->id)->update([
                    'customer_price_per_night' => $this->money($this->cents($room->customer_price_per_night) * $this->nights($room)),
                    'partner_price_per_night' => $this->money($this->cents($room->partner_price_per_night) * $this->nights($room)),
                ]);
            }
        });
        Schema::table('hotel_booking_rooms', function (Blueprint $table) {
            $table->renameColumn('customer_price_per_night', 'customer_sell');
            $table->renameColumn('partner_price_per_night', 'partner_share');
        });
    }

    private function nights(object $room): int
    {
        return (int) CarbonImmutable::parse($room->check_in_date)->diffInDays(CarbonImmutable::parse($room->check_out_date), false);
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
};
