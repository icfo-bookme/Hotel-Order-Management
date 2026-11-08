<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\HotelBookingService;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HotelSaleEditTest extends TestCase
{
    use RefreshDatabase;

    private function booking(bool $ticket = false): HotelBooking
    {
        $this->actingAsAdmin(User::factory()->create());
        $sale = $ticket ? ShipTicketSale::create(['customer_name' => 'Ticket Guest', 'customer_mobile' => '01712345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-11-08']) : null;
        return app(HotelBookingService::class)->sell($sale, [
            'customer_name' => 'Guest', 'customer_mobile' => '01712345678', 'whatsapp' => '+8801712345678',
            'rooms' => [[
                'bookme_hotel_id' => 523, 'hotel_name' => 'Hotel A', 'room_name' => 'Deluxe',
                'check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-12',
                'customer_price_per_night' => '2500', 'partner_price_per_night' => '2000',
            ]],
            'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '3000']],
            'payments' => [['amount' => '3000', 'other_fee' => '0', 'payment_method' => 'cash', 'paid_at' => '2026-11-08 12:00:37']],
        ], auth()->id());
    }

    private function payload(HotelBooking $booking): array
    {
        $data = $this->getJson(route('hotel-bookings.sale-edit', $booking))->assertOk()->json('data');
        return [
            'version' => $data['version'], 'customer_name' => $data['customer_name'],
            'customer_mobile' => $data['customer_mobile'], 'whatsapp' => $data['whatsapp'],
            'reservation_number' => $data['reservation_number'], 'remark' => $data['remark'],
            'rooms' => array_map(fn ($room) => [
                ...array_intersect_key($room, array_flip(['id', 'hotel_name', 'room_name', 'bookme_hotel_id', 'customer_price_per_night', 'partner_price_per_night', 'extra_bed_count', 'extra_bed_customer_rate', 'extra_bed_partner_rate'])),
                'check_in_date' => substr($room['check_in_date'], 0, 10), 'check_out_date' => substr($room['check_out_date'], 0, 10),
            ], $data['rooms']),
            'payments' => array_map(fn ($payment) => array_intersect_key($payment, array_flip(['id', 'amount', 'other_fee', 'payment_method', 'transaction_reference', 'paid_at', 'remark'])), $data['payments']),
            'hotel_advances' => array_map(fn ($advance) => ['bookme_hotel_id' => $advance['bookme_hotel_id'], 'amount' => $advance['advance_amount']], $data['advances']),
        ];
    }

    public function test_full_edit_updates_ticket_booking_without_changing_ticket_or_invoice_identity(): void
    {
        $booking = $this->booking(true);
        $invoiceNumber = app(HotelBookingService::class)->invoice($booking)['invoiceNumber'];
        $payload = $this->payload($booking);
        $payload['customer_name'] = 'Updated Guest';
        $payload['customer_mobile'] = '01912345678';
        $payload['whatsapp'] = null;
        $payload['reservation_number'] = 'UPDATED-RES';
        $payload['remark'] = 'Updated note';
        $payload['rooms'][0] = [...$payload['rooms'][0], 'bookme_hotel_id' => 426, 'hotel_name' => 'Hotel B', 'room_name' => 'Suite',
            'check_in_date' => '2026-11-11', 'check_out_date' => '2026-11-14', 'customer_price_per_night' => '3000',
            'partner_price_per_night' => '2200', 'extra_bed_count' => 1, 'extra_bed_customer_rate' => '200', 'extra_bed_partner_rate' => '100'];
        $payload['hotel_advances'] = [['bookme_hotel_id' => 426, 'amount' => '3000']];
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk()
            ->assertJsonPath('data.customer_name', 'Updated Guest')->assertJsonPath('data.rooms.0.room_name', 'Suite')
            ->assertJsonPath('data.totals.customer_sell', '9600.00')->assertJsonPath('data.totals.due', '6600.00');
        $this->assertSame($invoiceNumber, app(HotelBookingService::class)->invoice($booking->fresh())['invoiceNumber']);
        $this->assertSame('Ticket Guest', $booking->sale->fresh()->customer_name);
        $this->assertDatabaseMissing('hotel_booking_advances', ['hotel_booking_id' => $booking->id, 'bookme_hotel_id' => 523]);
        $this->assertDatabaseCount('hotel_booking_edits', 1);
        $this->get(route('hotel-bookings.invoice', $booking))->assertOk()->assertSee('Updated Guest')->assertSee('Suite')->assertSee('UPDATED-RES');
        $this->assertSame('Updated Guest', app(GoogleSheetService::class)->bookingRow($booking->fresh())[1]);
        $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->assertSee('data-edit-booking')->assertSee('Updated Guest');
    }

    public function test_payment_edits_preserve_unchanged_verification_and_require_reverification_when_changed(): void
    {
        Storage::fake('local');
        $booking = $this->booking();
        $payment = $booking->payments()->first();
        $payment->forceFill(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()])->save();
        $payload = $this->payload($booking);
        $payload['remark'] = 'Metadata change';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk()->assertJsonPath('data.payments.0.status', 'verified');
        $payload = $this->payload($booking);
        $payload['payments'][0]['amount'] = '3500';
        $payload['payments'][0]['attachment'] = UploadedFile::fake()->image('proof.png');
        $payload['hotel_advances'][0]['amount'] = '3500';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk()->assertJsonPath('data.payments.0.status', 'pending')->assertJsonPath('data.payments.0.verified_by', null);
        Storage::disk('local')->assertExists($payment->fresh()->attachment_path);
        $payload = $this->payload($booking);
        $payload['payments'][0]['remove_attachment'] = true;
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk()->assertJsonPath('data.payments.0.attachment_path', null);
    }

    public function test_add_remove_rooms_and_payments_and_empty_multipart_payments(): void
    {
        $booking = $this->booking();
        $oldRoom = $booking->rooms()->first();
        $payload = $this->payload($booking);
        unset($payload['rooms'][0]['id']);
        $payload['rooms'][0]['room_name'] = 'New Room';
        $payload['payments'] = [];
        $payload['hotel_advances'][0]['amount'] = '0';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk()->assertJsonPath('data.totals.received', '0.00');
        $this->assertSoftDeleted($oldRoom);
        $this->assertDatabaseCount('hotel_booking_payments', 0);
        $payload = $this->payload($booking);
        $payload['payments'] = [['amount' => '1000', 'payment_method' => 'bank', 'paid_at' => now()->toISOString()]];
        $payload['hotel_advances'][0]['amount'] = '1000';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk()->assertJsonPath('data.totals.received', '1000.00');
        $payload = $this->payload($booking);
        $payload['payments'] = '';
        $payload['hotel_advances'][0]['amount'] = '0';
        $payload['_method'] = 'PUT';
        $this->post(route('hotel-bookings.sale-update', $booking), $payload, ['Accept' => 'application/json'])->assertOk();
    }

    public function test_foreign_room_payment_and_stale_version_cannot_overwrite_booking(): void
    {
        $booking = $this->booking();
        $other = $this->booking();
        $payload = $this->payload($booking);
        $payload['customer_name'] = 'Should Roll Back';
        $payload['rooms'][0]['id'] = $other->rooms()->first()->id;
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable()->assertJsonValidationErrors('rooms.0.id');
        $this->assertSame('Guest', $booking->fresh()->customer_name);
        $payload = $this->payload($booking);
        $payload['payments'][0]['id'] = $other->payments()->first()->id;
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable()->assertJsonValidationErrors('payments.0.id');
        $payload = $this->payload($booking);
        $booking->rooms()->first()->update(['customer_price_per_night' => '2700']);
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertDatabaseCount('hotel_booking_edits', 0);
    }

    public function test_overpayment_and_invalid_dates_roll_back_all_changes_and_uploads(): void
    {
        Storage::fake('local');
        $booking = $this->booking();
        $payload = $this->payload($booking);
        $payload['rooms'][0]['check_out_date'] = '2026-11-09';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable();
        $payload = $this->payload($booking);
        $payload['customer_name'] = 'Rejected Guest';
        $payload['rooms'][0]['customer_price_per_night'] = '100';
        $payload['payments'][0]['attachment'] = UploadedFile::fake()->image('proof.png');
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable();
        $this->assertSame('Guest', $booking->fresh()->customer_name);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('hotel_booking_edits', 0);
    }

    public function test_payouts_allow_valid_edits_but_prevent_moving_or_overpaying_a_hotel(): void
    {
        $booking = $this->booking();
        $account = \App\Models\HotelPaymentAccount::create(['bookme_hotel_id' => 523, 'hotel_name' => 'Hotel A', 'payment_method' => 'cash', 'account_name' => 'Hotel A', 'account_number' => '1234', 'is_active' => true, 'is_default' => true]);
        $booking->payouts()->create(['bookme_hotel_id' => 523, 'hotel_payment_account_id' => $account->id, 'account_name' => 'Hotel A', 'account_number' => '1234', 'submission_id' => (string) \Illuminate\Support\Str::uuid(), 'payment_method' => 'cash', 'amount' => '1500', 'paid_at' => now(), 'created_by' => auth()->id()]);
        $payload = $this->payload($booking);
        $payload['rooms'][0]['customer_price_per_night'] = '2600';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk();
        $payload = $this->payload($booking);
        $payload['rooms'][0]['customer_price_per_night'] = '3000';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable()->assertJsonValidationErrors('hotel_advances');
        $this->assertSame('2600.00', $booking->rooms()->first()->customer_price_per_night);
        $payload = $this->payload($booking);
        $payload['rooms'][0]['bookme_hotel_id'] = 426;
        $payload['hotel_advances'][0]['bookme_hotel_id'] = 426;
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('hotel_payouts', 1);
    }

    public function test_refunds_and_reschedule_history_survive_edits(): void
    {
        $booking = $this->booking();
        $room = $booking->rooms()->first();
        $room->reschedules()->create(['old_check_in_date' => '2026-11-09', 'old_check_out_date' => '2026-11-11', 'new_check_in_date' => '2026-11-10', 'new_check_out_date' => '2026-11-12', 'customer_fee' => '500', 'partner_fee' => '100', 'reason' => 'Guest request', 'created_by' => auth()->id()]);
        $removed = $booking->rooms()->create($room->only(['bookme_hotel_id', 'hotel_name', 'room_name', 'check_in_date', 'check_out_date', 'customer_price_per_night', 'partner_price_per_night']));
        $removed->delete();
        $refund = $booking->payments()->make(['amount' => '-500', 'payment_method' => 'cash', 'paid_at' => now(), 'remark' => 'Refund', 'created_by' => auth()->id()]);
        $refund->forceFill(['refunded_room_id' => $removed->id, 'status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now()])->save();
        $booking->advances()->update(['advance_amount' => '2500']);
        $payload = $this->payload($booking);
        $payload['rooms'][0]['customer_price_per_night'] = '2700';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk()->assertJsonPath('data.totals.customer_sell', '5900.00')->assertJsonPath('data.totals.received', '2500.00');
        $this->assertDatabaseCount('hotel_booking_reschedules', 1);
        $payload = $this->payload($booking);
        $payload['payments'] = array_values(array_filter($payload['payments'], fn ($payment) => $payment['id'] !== $refund->id));
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable()->assertJsonValidationErrors('payments');
        $payload = $this->payload($booking);
        $payload['payments'][1]['amount'] = '-400';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnprocessable()->assertJsonValidationErrors('payments');
    }

    public function test_edit_permission_does_not_grant_payment_changes_or_allow_editing_other_statuses(): void
    {
        $booking = $this->booking();
        $payload = $this->payload($booking);
        $role = \App\Models\Role::create(['name' => 'Booking Editor']);
        $role->permissions()->sync(\App\Models\Permission::whereIn('name', ['bookings.update', 'bookings.sold.view'])->pluck('id'));
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id, 'is_active' => true])->save();
        $this->actingAs($user);
        $this->get('/hotel/sold')->assertOk()->assertSee('sell-payments-section')->assertSee('data-update-url');
        $payload['customer_name'] = 'Editor Change';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertOk();
        $payload = $this->payload($booking);
        $payload['payments'][0]['amount'] = '3200';
        $payload['hotel_advances'][0]['amount'] = '3200';
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertForbidden();
        $this->assertSame('3000.00', $booking->payments()->first()->amount);
        $payload = $this->payload($booking);
        $role->permissions()->detach();
        $this->getJson(route('hotel-bookings.sale-edit', $booking))->assertForbidden();
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertForbidden();
        $this->actingAsAdmin($user);
        $booking->update(['status' => 'cancelled']);
        $this->getJson(route('hotel-bookings.sale-edit', $booking))->assertUnprocessable();
        auth()->logout();
        $this->putJson(route('hotel-bookings.sale-update', $booking), $payload)->assertUnauthorized();
    }
}
