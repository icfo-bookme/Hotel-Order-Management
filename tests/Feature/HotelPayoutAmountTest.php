<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\HotelBookingPayment;
use App\Models\HotelBookingRoom;
use App\Models\HotelBookingAdvance;
use App\Models\HotelPayout;
use App\Services\HotelPayoutService;
use Tests\TestCase;

class HotelPayoutAmountTest extends TestCase
{
    public function test_payout_uses_payment_amounts_minus_room_profit_and_ignores_fees(): void
    {
        $room = new HotelBookingRoom([
            'bookme_hotel_id' => 523,
            'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22',
            'customer_price_per_night' => '5000.15', 'partner_price_per_night' => '4000.10',
        ]);
        $booking = new HotelBooking();
        $booking->setRelation('rooms', collect([$room]));
        $first = new HotelBookingPayment(['amount' => '3000.25', 'other_fee' => '100.00']);
        $second = new HotelBookingPayment(['amount' => '4000.50', 'other_fee' => '200.00']);
        $booking->setRelation('payments', collect([$first, $second]));
        $advance = new HotelBookingAdvance(['bookme_hotel_id' => 523, 'advance_amount' => '7000.75']);
        $advance->setRelation('booking', $booking);
        $booking->setRelation('advances', collect([$advance]));
        $booking->setRelation('accounts', collect());
        $booking->setRelation('payouts', collect());
        $service = app(HotelPayoutService::class);
        $this->assertSame('5000.65', $service->balances($advance)['payout_amount']);
        $first->other_fee = '999.99';
        $this->assertSame('5000.65', $service->balances($advance)['payout_amount']);
        $booking->setRelation('payouts', collect([new HotelPayout(['bookme_hotel_id' => 523, 'amount' => '500.00'])]));
        $this->assertSame('4500.65', $service->balances($advance)['remaining']);
    }
}
