<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\HotelPaymentAccount;
use App\Models\HotelPayout;
use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\HotelBookingService;
use App\Services\HotelPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HotelRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private function booking(): HotelBooking
    {
        $this->actingAs(User::factory()->create());
        $sale = ShipTicketSale::create(['customer_name' => 'Guest', 'customer_mobile' => '01712345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-18', 'hotel_status' => 'sold']);
        $booking = $sale->hotelBookings()->create(['status' => 'confirmed', 'created_by' => auth()->id()]);
        foreach ([523, 426] as $hotelId) {
            $booking->rooms()->create(['hotel_name' => 'Hotel '.$hotelId, 'bookme_hotel_id' => $hotelId, 'room_name' => 'Deluxe', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => 5000, 'partner_price_per_night' => 4000]);
            $booking->advances()->create(['bookme_hotel_id' => $hotelId, 'hotel_name' => 'Hotel '.$hotelId, 'advance_amount' => 4000]);
            $account = HotelPaymentAccount::create(['bookme_hotel_id' => $hotelId, 'hotel_name' => 'Hotel', 'payment_method' => 'bank', 'account_name' => 'Hotel', 'account_number' => '123', 'is_active' => true]);
        }
        $payment = $booking->payments()->create(['amount' => 8000, 'payment_method' => 'cash', 'paid_at' => now()]);
        $payment->forceFill(['status' => 'verified'])->save();

        return $booking;
    }

    private function payload(): array
    {
        return ['old_check_in_date' => '2026-10-20', 'old_check_out_date' => '2026-10-22', 'new_check_in_date' => '2026-11-01', 'new_check_out_date' => '2026-11-04', 'customer_fee' => '500.25', 'partner_fee' => '200.10', 'reason' => 'Guest requested new dates'];
    }

    public function test_reschedule_updates_dates_fees_payment_allocation_and_verified_reports(): void
    {
        Storage::fake('local');
        $booking = $this->booking();
        $room = $booking->rooms()->first();
        $data = $this->payload();
        $data['payment'] = ['amount' => '2000', 'other_fee' => '50', 'payment_method' => 'bkash', 'paid_at' => now()->toDateTimeString(), 'transaction_reference' => 'RES-123', 'attachment' => UploadedFile::fake()->image('proof.png')];
        $this->getJson(route('hotel-sales.reschedules', $booking->sale))->assertOk()->assertJsonCount(2, 'rooms');
        $this->postJson(route('hotel-booking-rooms.reschedule', $room), $data)->assertOk();
        $this->assertDatabaseHas('hotel_booking_reschedules', ['hotel_booking_room_id' => $room->id, 'created_by' => auth()->id()]);
        $this->assertSame('2026-10-20', $room->reschedules()->first()->old_check_in_date->format('Y-m-d'));
        $this->assertSame('2026-11-01', $room->fresh()->check_in_date->format('Y-m-d'));
        $totals = app(HotelBookingService::class)->totals($booking->fresh());
        $this->assertSame('25500.25', $totals['customer_sell']);
        $this->assertSame('20200.10', $totals['partner_share']);
        $this->assertSame('10000.00', $totals['received']);
        $this->assertDatabaseHas('hotel_booking_advances', ['hotel_booking_id' => $booking->id, 'bookme_hotel_id' => 523, 'advance_amount' => 6000]);
        $this->assertDatabaseHas('hotel_booking_advances', ['hotel_booking_id' => $booking->id, 'bookme_hotel_id' => 426, 'advance_amount' => 4000]);
        $payment = $booking->payments()->latest('id')->first();
        $this->assertSame('pending', $payment->status);
        Storage::disk('local')->assertExists($payment->attachment_path);
        $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->assertSee('Reschedule');
        $filters = ['date_from' => now()->format('Y-m-d'), 'date_to' => now()->format('Y-m-d')];
        $this->getJson(route('reports.hotel-sales.data', $filters))->assertOk()->assertJsonPath('summary.bookings', 0);
        $this->postJson(route('hotel-sales.verify-payments', $booking->sale))->assertOk();
        $this->getJson(route('reports.hotel-sales.data', $filters))->assertOk()->assertJsonPath('summary.sell', '25500.25')->assertJsonPath('summary.partner', '20200.10')->assertSee('01 Nov 2026');
        $advance = $booking->advances()->where('bookme_hotel_id', 523)->first();
        $this->assertSame('2699.85', app(HotelPayoutService::class)->balances($advance)['payout_amount']);
        $this->postJson(route('hotel-booking-rooms.reschedule', $room), $data)->assertUnprocessable()->assertJsonValidationErrors('room');
        $this->assertSame(1, $room->reschedules()->count());
        $this->assertSame(2, $booking->payments()->count());
    }

    public function test_invalid_dates_refunds_and_paid_bookings_do_not_mutate_data(): void
    {
        $booking = $this->booking();
        $room = $booking->rooms()->first();
        $url = route('hotel-booking-rooms.reschedule', $room);
        $this->postJson($url, array_replace($this->payload(), ['new_check_out_date' => '2026-11-01']))->assertUnprocessable();
        $room->update(['customer_price_per_night' => 100]);
        $this->postJson($url, $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('hotel_booking_reschedules', 0);
        $room->update(['customer_price_per_night' => 5000]);
        $account = HotelPaymentAccount::where('bookme_hotel_id', 523)->firstOrFail();
        HotelPayout::create(['submission_id' => (string) Str::uuid(), 'hotel_booking_id' => $booking->id, 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $account->id, 'amount' => 100, 'paid_at' => now(), 'payment_method' => 'bank', 'account_name' => 'Hotel', 'account_number' => '123']);
        $this->postJson($url, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('booking');
        $this->assertDatabaseCount('hotel_booking_reschedules', 0);
        $this->assertSame('2026-10-20', $room->fresh()->check_in_date->format('Y-m-d'));
        auth()->logout();
        $this->postJson($url, $this->payload())->assertUnauthorized();
    }
}
