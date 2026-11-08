<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\HotelPaymentAccount;
use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectHotelBookingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'customer_name' => 'Walk-in Guest', 'customer_mobile' => '01712345678', 'whatsapp' => '+8801712345678',
            'rooms' => [[
                'bookme_hotel_id' => 523, 'hotel_name' => 'Hotel A', 'room_name' => 'Deluxe',
                'check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-12',
                'customer_price_per_night' => '2500', 'partner_price_per_night' => '2000',
            ]],
            'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '3000']],
            'payments' => [['amount' => '3000', 'payment_method' => 'cash', 'paid_at' => '2026-11-08 12:00:00']],
        ];
    }

    public function test_direct_booking_invoice_sheet_verification_report_and_payout(): void
    {
        $this->actingAsAdmin(User::factory()->create());
        $response = $this->postJson(route('hotel-direct-bookings.store'), $this->payload())->assertCreated()
            ->assertJsonPath('data.sales_id', null)->assertJsonPath('data.customer_name', 'Walk-in Guest');
        $booking = HotelBooking::findOrFail($response->json('data.id'));
        $this->assertDatabaseCount('ship_ticket_sales', 0);
        $this->get(route('hotel-bookings.invoice', $booking))->assertOk()->assertSee('Walk-in Guest')->assertSee('+8801712345678');
        $this->assertSame(['Walk-in Guest', '01712345678', '+8801712345678'], array_slice(app(GoogleSheetService::class)->bookingRow($booking), 1, 3));
        $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.direct_booking_id', $booking->id);
        $this->getJson('/ship-tickets/data/sold')->assertJsonPath('recordsTotal', 0);
        $this->getJson(route('hotel-direct-bookings.rooms', $booking))->assertOk()->assertJsonPath('rooms.0.hotel_name', 'Hotel A');
        $this->getJson(route('hotel-direct-bookings.reschedules', $booking))->assertOk();
        $this->getJson(route('hotel-direct-bookings.removable-rooms', $booking))->assertOk()->assertJsonPath('rooms.0.last_room', true);

        $account = HotelPaymentAccount::create(['bookme_hotel_id' => 523, 'hotel_name' => 'Hotel A', 'payment_method' => 'bank', 'account_name' => 'Hotel', 'account_number' => '1234', 'is_active' => true, 'is_default' => true]);
        $advance = $booking->advances()->first();
        $payout = ['hotel_payment_account_id' => $account->id, 'amount' => '1000', 'submission_id' => (string) Str::uuid()];
        $this->postJson(route('hotel-payouts.store', $advance), $payout)->assertUnprocessable();
        $this->postJson(route('hotel-direct-bookings.verify-payments', $booking))->assertOk()->assertJsonPath('verified_count', 1);
        $this->getJson('/ship-tickets/data/pending-sold')->assertJsonPath('recordsTotal', 0);
        $this->getJson('/ship-tickets/data/sold')->assertJsonPath('recordsTotal', 1);
        $this->getJson(route('hotel-payouts.pending.data'))->assertOk()->assertSee('Walk-in Guest');
        $this->getJson('/reports/hotel-sales/data?date_from=2026-01-01&date_to=2027-01-01&q=Walk-in')->assertOk()->assertJsonPath('recordsFiltered', 1)->assertSee('Walk-in Guest');
        $this->get('/reports/hotel-sales/export?date_from=2026-01-01&date_to=2027-01-01&q=Walk-in')->assertOk();
        $this->postJson(route('hotel-payouts.store', $advance), $payout)->assertCreated();
        $this->getJson(route('hotel-payouts.paid.data'))->assertOk()->assertSee('Walk-in Guest');
        $this->patchJson(route('hotel-bookings.advances', $booking), ['hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '3000']]])->assertOk();
    }

    public function test_ticket_customer_is_copied_and_blank_whatsapp_can_be_supplied(): void
    {
        $this->actingAsAdmin(User::factory()->create());
        $sale = ShipTicketSale::create(['customer_name' => 'Ticket Guest', 'customer_mobile' => '01912345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-11-08']);
        $payload = $this->payload();
        unset($payload['customer_name'], $payload['customer_mobile']);
        $response = $this->postJson(route('hotel-sales.sell', $sale), $payload)->assertCreated()
            ->assertJsonPath('data.customer_name', 'Ticket Guest')->assertJsonPath('data.customer_mobile', '01912345678')
            ->assertJsonPath('data.whatsapp', '+8801712345678');
        $sale->update(['customer_name' => 'Changed Later']);
        $this->get(route('hotel-bookings.invoice', $response->json('data.id')))->assertOk()->assertSee('Ticket Guest')->assertDontSee('Changed Later');
        $this->assertNull($sale->fresh()->whatsapp);
        $this->get('/hotel/pending')->assertOk()->assertSee('sell-customer-name')->assertSee('sell-customer-mobile')->assertSee('sell-whatsapp')->assertSee('New Hotel Booking');
    }

    public function test_direct_booking_validation_and_transaction_rollback(): void
    {
        $this->postJson(route('hotel-direct-bookings.store'), $this->payload())->assertUnauthorized();
        $this->actingAsAdmin(User::factory()->create());
        $payload = $this->payload();
        unset($payload['customer_name'], $payload['customer_mobile']);
        $this->postJson(route('hotel-direct-bookings.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['customer_name', 'customer_mobile']);
        $payload = $this->payload();
        $payload['hotel_advances'][0]['amount'] = '2000';
        $this->postJson(route('hotel-direct-bookings.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('hotel_bookings', 0);
        $this->assertDatabaseCount('hotel_booking_payments', 0);
        $payload = $this->payload();
        $payload['whatsapp'] = null;
        $this->postJson(route('hotel-direct-bookings.store'), $payload)->assertCreated()->assertJsonPath('data.whatsapp', null);
    }

    public function test_ticket_and_direct_rows_remain_separate_and_searchable(): void
    {
        $this->actingAsAdmin(User::factory()->create());
        $directId = $this->postJson(route('hotel-direct-bookings.store'), $this->payload())->assertCreated()->json('data.id');
        $sale = ShipTicketSale::create(['customer_name' => 'Ticket Guest', 'customer_mobile' => '01912345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-11-08']);
        $payload = $this->payload();
        $payload['customer_name'] = 'Updated Guest';
        $this->postJson(route('hotel-sales.sell', $sale), $payload)->assertCreated();
        $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->assertJsonPath('recordsTotal', 2)
            ->assertSee('Walk-in Guest')->assertSee('Updated Guest')->assertDontSee('Ticket Guest');
        $this->getJson('/ship-tickets/data/pending-sold?'.http_build_query([
            'draw' => 1, 'start' => 0, 'length' => 1, 'search' => ['value' => 'Walk-in'],
            'columns' => [['data' => 'customer_name', 'searchable' => 'true', 'orderable' => 'true']],
        ]))->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.direct_booking_id', $directId);
        $this->postJson(route('hotel-direct-bookings.verify-payments', $directId))->assertOk();
        $this->getJson('/ship-tickets/data/pending-sold')->assertJsonPath('recordsTotal', 1);
        $this->getJson('/ship-tickets/data/sold')->assertJsonPath('recordsTotal', 1);
        $this->assertSame('pending', $sale->hotelBookings()->first()->payments()->first()->status);
    }

    public function test_direct_booking_actions_require_their_permissions(): void
    {
        $this->actingAsAdmin(User::factory()->create());
        $id = $this->postJson(route('hotel-direct-bookings.store'), $this->payload())->assertCreated()->json('data.id');
        $role = \App\Models\Role::create(['name' => 'No Booking Access']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id, 'is_active' => true])->save();
        $this->actingAs($user);
        $this->postJson(route('hotel-direct-bookings.store'), $this->payload())->assertForbidden();
        $this->getJson(route('hotel-direct-bookings.rooms', $id))->assertForbidden();
        $this->getJson(route('hotel-direct-bookings.reschedules', $id))->assertForbidden();
        $this->getJson(route('hotel-direct-bookings.removable-rooms', $id))->assertForbidden();
        $this->postJson(route('hotel-direct-bookings.verify-payments', $id))->assertForbidden();
    }

    public function test_direct_booking_reschedule_delete_and_restore(): void
    {
        $user = User::factory()->create();
        $this->actingAsAdmin($user);
        $id = $this->postJson(route('hotel-direct-bookings.store'), $this->payload())->assertCreated()->json('data.id');
        $booking = HotelBooking::findOrFail($id);
        $room = $booking->rooms()->first();
        $this->postJson(route('hotel-booking-rooms.reschedule', $room), [
            'old_check_in_date' => '2026-11-10', 'old_check_out_date' => '2026-11-12',
            'new_check_in_date' => '2026-11-11', 'new_check_out_date' => '2026-11-13',
            'customer_fee' => '0', 'partner_fee' => '0', 'reason' => 'Guest request',
        ])->assertOk();
        $this->deleteJson(route('hotel-bookings.destroy', $booking), ['delete_reason' => 'Duplicate'])->assertOk();
        $this->getJson('/ship-tickets/data/pending-sold')->assertJsonPath('recordsTotal', 0);
        $this->getJson(route('hotel-bookings.trash.data'))->assertOk()->assertSee('Walk-in Guest');
        $this->postJson(route('hotel-bookings.restore', $booking))->assertOk();
        $this->getJson('/ship-tickets/data/pending-sold')->assertJsonPath('recordsTotal', 1);
    }
}
