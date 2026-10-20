<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\HotelPaymentAccount;
use App\Models\HotelPayout;
use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\HotelBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HotelBookingSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function booking(): HotelBooking
    {
        if (!auth()->check()) {
            $this->actingAsAdmin(User::factory()->create());
        }
        $sale = ShipTicketSale::create(['customer_name' => 'Trash Customer', 'customer_mobile' => '01712345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-20']);

        return app(HotelBookingService::class)->sell($sale, [
            'rooms' => [['bookme_hotel_id' => 523, 'hotel_name' => 'Hotel', 'room_name' => 'Deluxe', 'check_in_date' => '2026-10-21', 'check_out_date' => '2026-10-23', 'customer_price_per_night' => 5000, 'partner_price_per_night' => 4000]],
            'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => 0]],
        ], (int) auth()->id());
    }

    public function test_unpaid_booking_moves_to_trash_and_restores_without_losing_children(): void
    {
        $booking = $this->booking();
        $room = $booking->rooms()->first();
        $room->reschedules()->create(['old_check_in_date' => '2026-10-19', 'old_check_out_date' => '2026-10-21', 'new_check_in_date' => '2026-10-21', 'new_check_out_date' => '2026-10-23', 'customer_fee' => 0, 'partner_fee' => 0, 'reason' => 'Dates changed', 'created_by' => auth()->id()]);
        $this->deleteJson(route('hotel-bookings.destroy', $booking), ['delete_reason' => '<script>Wrong entry</script>'])->assertOk();
        $this->assertSoftDeleted('hotel_bookings', ['id' => $booking->id]);
        $this->assertDatabaseHas('hotel_bookings', ['id' => $booking->id, 'deleted_by' => auth()->id(), 'delete_reason' => '<script>Wrong entry</script>']);
        $this->assertDatabaseHas('hotel_booking_rooms', ['id' => $room->id]);
        $this->assertDatabaseCount('hotel_booking_reschedules', 1);
        $this->assertDatabaseCount('hotel_booking_advances', 1);
        $this->assertSame('pending', $booking->sale->fresh()->hotel_status);
        $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->getJson(route('hotel-bookings.index'))->assertOk()->assertJsonPath('total', 0);
        $this->getJson(route('hotel-bookings.show', $booking))->assertNotFound();
        $this->get(route('hotel-bookings.invoice', $booking))->assertNotFound();
        $this->getJson(route('hotel-booking-rooms.show', $room))->assertNotFound();
        $this->patchJson(route('hotel-booking-rooms.update', $room), ['room_name' => 'Changed'])->assertNotFound();
        $this->postJson(route('hotel-booking-payments.store'), ['hotel_booking_id' => $booking->id, 'amount' => 100, 'payment_method' => 'cash', 'paid_at' => now()->toDateTimeString()])->assertUnprocessable()->assertJsonValidationErrors('hotel_booking_id');
        $this->get(route('hotel-bookings.trash'))->assertOk()->assertSee('restore-booking-modal');
        $trash = $this->getJson(route('hotel-bookings.trash.data'))->assertOk()->assertJsonPath('recordsTotal', 1);
        $trash->assertJsonPath('data.0.delete_reason', '&lt;script&gt;Wrong entry&lt;/script&gt;');
        $this->postJson(route('hotel-bookings.restore', $booking))->assertOk();
        $this->assertNotSoftDeleted('hotel_bookings', ['id' => $booking->id]);
        $this->assertSame('sold', $booking->sale->fresh()->hotel_status);
        $this->getJson(route('hotel-bookings.trash.data'))->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->get(route('hotel-bookings.invoice', $booking))->assertOk();
        $this->postJson(route('hotel-bookings.restore', $booking))->assertNotFound();
    }

    public function test_customer_payments_are_preserved_and_only_payouts_block_deletion(): void
    {
        foreach (['pending', 'verified', 'rejected'] as $status) {
            $booking = $this->booking();
            $payment = $booking->payments()->create(['amount' => 100, 'payment_method' => 'cash', 'paid_at' => now()]);
            $payment->forceFill(['status' => $status])->save();
            $this->deleteJson(route('hotel-bookings.destroy', $booking), ['delete_reason' => 'Mistake'])->assertOk();
            $this->assertSoftDeleted('hotel_bookings', ['id' => $booking->id]);
            $this->assertDatabaseHas('hotel_booking_payments', ['id' => $payment->id, 'status' => $status, 'amount' => 100]);
            $this->assertSame('pending', $booking->sale->fresh()->hotel_status);
            $this->postJson(route('hotel-bookings.restore', $booking))->assertOk();
            $this->assertSame($status, $payment->fresh()->status);
        }
        $booking = $this->booking();
        $account = HotelPaymentAccount::create(['bookme_hotel_id' => 523, 'hotel_name' => 'Hotel', 'payment_method' => 'cash', 'account_name' => 'Hotel', 'account_number' => '123', 'is_active' => true]);
        HotelPayout::create(['submission_id' => (string) Str::uuid(), 'hotel_booking_id' => $booking->id, 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $account->id, 'amount' => 100, 'paid_at' => now(), 'payment_method' => 'cash', 'account_name' => 'Hotel', 'account_number' => '123']);
        $this->deleteJson(route('hotel-bookings.destroy', $booking), ['delete_reason' => 'Mistake'])->assertUnprocessable()->assertJsonValidationErrors('booking');
        $this->assertNotSoftDeleted('hotel_bookings', ['id' => $booking->id]);
    }

    public function test_restore_blocks_replacement_and_deleting_one_booking_keeps_other_sale_active(): void
    {
        $booking = $this->booking();
        $sale = $booking->sale;
        $sale->remark()->create(['remark_text' => 'Interested', 'added_by' => auth()->id()]);
        $this->deleteJson(route('hotel-bookings.destroy', $booking), ['delete_reason' => 'Duplicate'])->assertOk();
        $this->assertSame('feedbacked', $sale->fresh()->hotel_status);
        $replacement = app(HotelBookingService::class)->create(['sales_id' => $sale->id, 'status' => 'confirmed'], (int) auth()->id());
        $this->postJson(route('hotel-bookings.restore', $booking))->assertUnprocessable()->assertJsonValidationErrors('booking');
        $this->assertSoftDeleted('hotel_bookings', ['id' => $booking->id]);
        $other = $sale->hotelBookings()->create(['status' => 'confirmed']);
        $this->deleteJson(route('hotel-bookings.destroy', $replacement), ['delete_reason' => 'Duplicate'])->assertOk();
        $this->assertSame('sold', $sale->fresh()->hotel_status);
        $this->assertNotSoftDeleted('hotel_bookings', ['id' => $other->id]);
    }

    public function test_archived_parents_are_excluded_from_reports_payouts_and_private_payment_views(): void
    {
        Storage::fake('local');
        $booking = $this->booking();
        $payment = $booking->payments()->create(['amount' => 3000, 'payment_method' => 'cash', 'paid_at' => now(), 'attachment_path' => 'proof.png']);
        $payment->forceFill(['status' => 'verified'])->save();
        $booking->advances()->update(['advance_amount' => 3000]);
        Storage::disk('local')->put('proof.png', 'image');
        $booking->delete();
        $filters = ['date_from' => now('Asia/Dhaka')->toDateString(), 'date_to' => now('Asia/Dhaka')->toDateString()];
        $this->getJson(route('reports.hotel-sales.data', $filters))->assertOk()->assertJsonPath('recordsTotal', 0)->assertJsonPath('summary.received', '0.00');
        $this->getJson(route('hotel-payouts.pending.data'))->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->getJson(route('hotel-booking-payments.show', $payment))->assertNotFound();
        $this->get(route('hotel-booking-payments.attachment', $payment))->assertNotFound();
        $this->assertFalse(ShipTicketSale::whereKey($booking->sales_id)->withVerifiedHotelPayments()->exists());
    }

    public function test_delete_requires_reason_and_authentication(): void
    {
        $booking = $this->booking();
        $this->deleteJson(route('hotel-bookings.destroy', $booking))->assertUnprocessable()->assertJsonValidationErrors('delete_reason');
        auth()->logout();
        $this->deleteJson(route('hotel-bookings.destroy', $booking), ['delete_reason' => 'Mistake'])->assertUnauthorized();
        $this->getJson(route('hotel-bookings.trash.data'))->assertUnauthorized();
        $this->postJson(route('hotel-bookings.restore', $booking))->assertUnauthorized();
    }
}
