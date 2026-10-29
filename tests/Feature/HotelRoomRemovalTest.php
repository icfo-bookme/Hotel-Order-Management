<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\HotelBookingRoom;
use App\Models\HotelPaymentAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\GoogleSheetService;
use App\Services\HotelBookingService;
use App\Services\HotelPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HotelRoomRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function booking(string $received = '14000', bool $verified = true, bool $differentHotels = false): HotelBooking
    {
        $this->actingAsAdmin(User::factory()->create());
        $sale = ShipTicketSale::create([
            'customer_name' => 'Customer', 'customer_mobile' => '01712345678', 'ship_id' => 1,
            'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-29',
        ]);
        $room = [
            'bookme_hotel_id' => 523, 'hotel_name' => 'Hotel One', 'room_name' => 'Deluxe',
            'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-03',
            'customer_price_per_night' => '5000', 'partner_price_per_night' => '4000',
        ];
        $booking = app(HotelBookingService::class)->sell($sale, [
            'rooms' => [$room, array_merge($room, ['room_name' => 'Suite'], $differentHotels ? ['bookme_hotel_id' => 426, 'hotel_name' => 'Hotel Two'] : [])],
            'payments' => [['amount' => $received, 'payment_method' => 'cash', 'paid_at' => now()]],
            'hotel_advances' => $differentHotels
                ? [['bookme_hotel_id' => 523, 'amount' => '7000'], ['bookme_hotel_id' => 426, 'amount' => '7000']]
                : [['bookme_hotel_id' => 523, 'amount' => $received]],
        ], auth()->id());
        if ($verified) {
            $booking->payments()->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()]);
        }
        return $booking->fresh(['rooms', 'payments', 'advances']);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'refund_amount' => '4000', 'hotel_refund_amount' => '0', 'payment_method' => 'cash',
            'reason' => 'Customer no longer needs this room', 'confirmed' => 1,
        ], $overrides);
    }

    public function test_room_refund_adjusts_sale_invoice_report_export_and_sheet_values(): void
    {
        $booking = $this->booking();
        $room = $booking->rooms->first();
        $this->getJson(route('hotel-sales.removable-rooms', $booking->sale))->assertOk()->assertJsonCount(2, 'rooms');
        $this->postJson(route('hotel-booking-rooms.remove', $room), $this->payload())
            ->assertOk()->assertJsonPath('totals.customer_sell', '10000.00')
            ->assertJsonPath('totals.partner_share', '8000.00')->assertJsonPath('totals.profit', '2000.00')
            ->assertJsonPath('totals.received', '10000.00')->assertJsonPath('totals.due', '0.00');
        $this->assertSoftDeleted($room);
        $this->assertDatabaseHas('hotel_booking_rooms', ['id' => $room->id, 'removed_by' => auth()->id(), 'removal_reason' => $this->payload()['reason']]);
        $this->assertDatabaseHas('hotel_booking_payments', ['hotel_booking_id' => $booking->id, 'refunded_room_id' => $room->id, 'amount' => -4000, 'status' => 'verified']);
        $this->assertSame('14000.00', $booking->payments->first()->fresh()->amount);
        $this->assertSame('10000.00', $booking->advances->first()->fresh()->advance_amount);
        $this->get(route('hotel-bookings.invoice', $booking))->assertOk()->assertSee('Suite')->assertDontSee('Deluxe')->assertSee('Room Refund');
        $this->getJson(route('ship-tickets.data', ['status' => 'sold']))->assertOk()->assertJsonCount(1, 'data')->assertSee('Room Refund');
        $dates = ['date_from' => '2020-01-01', 'date_to' => '2030-12-31'];
        $this->getJson(route('reports.hotel-sales.data', $dates))->assertOk()
            ->assertJsonPath('summary.sell', '10000.00')->assertJsonPath('summary.partner', '8000.00')
            ->assertJsonPath('summary.received', '10000.00')->assertJsonPath('summary.due', '0.00');
        $export = $this->get(route('reports.hotel-sales.export', $dates))->assertOk()->streamedContent();
        $this->assertStringContainsString('10000.00,8000.00,2000.00,10000.00,0.00', $export);
        $this->assertSame(['10000.00', '8000.00', '2000.00', '10000.00', '0.00'], array_slice(app(GoogleSheetService::class)->bookingRow($booking->fresh()), 6, 5));
        $this->postJson(route('hotel-booking-rooms.remove', $room), $this->payload())->assertNotFound();
        $this->assertSame(1, $booking->payments()->whereNotNull('refunded_room_id')->count());
        $this->patchJson(route('hotel-booking-payments.update', $booking->payments->first()), ['amount' => '1000'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->postJson(route('hotel-booking-rooms.reschedule', $room), [])->assertNotFound();
    }

    public function test_pending_sale_room_can_be_removed_without_refunding_unverified_money(): void
    {
        $booking = $this->booking('3000', false);
        $this->postJson(route('hotel-booking-rooms.remove', $booking->rooms->first()), $this->payload(['refund_amount' => '1000']))
            ->assertUnprocessable()->assertJsonValidationErrors('refund_amount');
        $this->postJson(route('hotel-booking-rooms.remove', $booking->rooms->first()), $this->payload(['refund_amount' => '0']))
            ->assertOk()->assertJsonPath('totals.received', '3000.00')->assertJsonPath('totals.due', '7000.00');
        $this->assertSame(1, $booking->payments()->count());
    }

    public function test_hotel_recovery_adjusts_paid_and_remaining_payouts(): void
    {
        $booking = $this->booking();
        $account = HotelPaymentAccount::create([
            'bookme_hotel_id' => 523, 'hotel_name' => 'Hotel One', 'payment_method' => 'bank',
            'account_name' => 'Hotel', 'account_number' => '1234', 'bank_name' => 'Bank', 'is_active' => true,
        ]);
        app(HotelPayoutService::class)->create($booking->advances->first(), [
            'submission_id' => (string) Str::uuid(), 'amount' => '10000', 'hotel_payment_account_id' => $account->id,
        ], auth()->id());
        $room = $booking->rooms->first();
        $this->postJson(route('hotel-booking-rooms.remove', $room), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('hotel_refund_amount');
        $this->assertNotNull(HotelBookingRoom::find($room->id));
        $this->assertSame(1, $booking->payments()->count());
        $this->postJson(route('hotel-booking-rooms.remove', $room), $this->payload(['hotel_refund_amount' => '2000']))->assertOk();
        $advance = $booking->advances->first()->fresh();
        $balance = app(HotelPayoutService::class)->balances($advance);
        $this->assertSame('8000.00', $balance['paid']);
        $this->assertSame('0.00', $balance['remaining']);
        $this->getJson(route('hotel-payouts.pending.data'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('hotel-payouts.paid.data'))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(route('reports.hotel-sales.data', ['date_from' => '2020-01-01', 'date_to' => '2030-12-31']))
            ->assertOk()->assertJsonPath('summary.paid', '8000.00');
    }

    public function test_removing_a_hotels_only_room_clears_its_advance_and_keeps_other_hotel_unchanged(): void
    {
        $booking = $this->booking(differentHotels: true);
        $this->postJson(route('hotel-booking-rooms.remove', $booking->rooms->first()), $this->payload(['refund_amount' => '7000']))
            ->assertOk()->assertJsonPath('totals.customer_sell', '10000.00')->assertJsonPath('totals.received', '7000.00');
        $this->assertSame(1, $booking->advances()->count());
        $this->assertSame('7000.00', $booking->advances()->first()->advance_amount);
        $this->assertSame(426, $booking->advances()->first()->bookme_hotel_id);
    }

    public function test_last_room_invalid_refunds_and_unconfirmed_requests_are_rejected_atomically(): void
    {
        $booking = $this->booking();
        $room = $booking->rooms->first();
        foreach ([['refund_amount' => '-1'], ['refund_amount' => '15000'], ['refund_amount' => '0'], ['refund_amount' => '4000.001'], ['hotel_refund_amount' => '1'], ['confirmed' => 0]] as $invalid) {
            $this->postJson(route('hotel-booking-rooms.remove', $room), $this->payload($invalid))->assertUnprocessable();
            $this->assertSame(2, $booking->rooms()->count());
            $this->assertSame(1, $booking->payments()->count());
        }
        $this->postJson(route('hotel-booking-rooms.remove', $room), $this->payload())->assertOk();
        $this->postJson(route('hotel-booking-rooms.remove', $booking->rooms->last()), $this->payload(['refund_amount' => '0']))
            ->assertUnprocessable()->assertJsonValidationErrors('room');
    }

    public function test_room_and_money_permissions_are_enforced(): void
    {
        $booking = $this->booking('3000');
        $role = Role::create(['name' => 'Room Editor']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($user);
        $this->getJson(route('hotel-sales.removable-rooms', $booking->sale))->assertForbidden();
        $role->permissions()->attach(Permission::where('name', 'bookings.update')->value('id'));
        $this->getJson(route('hotel-sales.removable-rooms', $booking->sale))->assertOk();
        $this->postJson(route('hotel-booking-rooms.remove', $booking->rooms->first()), $this->payload(['refund_amount' => '1']))->assertForbidden();
        $this->postJson(route('hotel-booking-rooms.remove', $booking->rooms->first()), $this->payload(['refund_amount' => '0', 'hotel_refund_amount' => '1']))->assertForbidden();
        $this->postJson(route('hotel-booking-rooms.remove', $booking->rooms->first()), $this->payload(['refund_amount' => '0.00']))->assertOk();
    }
}
