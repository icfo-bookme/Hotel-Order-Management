<?php

namespace Tests\Feature;

use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\HotelBookingService;
use App\Services\HotelRemarkService;
use App\Services\ShipTicketQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HotelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_modals_are_rendered_outside_the_inert_app_shell(): void
    {
        $user = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password']);
        foreach (['/hotel/pending' => ['sell-modal', 'remarks-modal'], '/hotel/feedbacked' => ['sell-modal', 'edit-feedback-modal'], '/hotel/sold' => ['reschedule-modal', 'reschedule-charges-modal', 'payment-screenshot-modal'], '/profile' => ['confirm-user-deletion']] as $url => $ids) {
            $response = $this->actingAs($user)->get($url)->assertOk();
            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(count($ids), $xpath->query('//*[@data-modal]')->length);
            foreach ($ids as $id) {
                $this->assertSame(1, $xpath->query('//*[@data-modal="'.$id.'"]')->length);
                $this->assertSame(0, $xpath->query('//*[@id="app-shell"]//*[@data-modal="'.$id.'"]')->length);
            }
            if ($url === '/hotel/pending') {
                $this->assertSame('sell-form', $xpath->query('//*[@id="submit-sell"]')->item(0)->getAttribute('form'));
            }
            if ($url === '/hotel/feedbacked') {
                $this->assertSame('edit-feedback-form', $xpath->query('//*[@id="update-feedback"]')->item(0)->getAttribute('form'));
            }
        }
    }

    public function test_sold_payments_can_be_verified_without_changing_received_or_due(): void
    {
        $user = User::factory()->create();
        $sale = $this->sale();
        $booking = app(HotelBookingService::class)->sell($sale, ['rooms' => [[
            'hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $this->hotelAccountId(), 'room_name' => 'Deluxe',
            'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22',
            'customer_price_per_night' => '5000.00', 'partner_price_per_night' => '4000.00',
        ]], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '3000']], 'payments' => [
            ['amount' => '1000', 'payment_method' => 'cash', 'paid_at' => now()],
            ['amount' => '2000', 'payment_method' => 'bkash', 'paid_at' => now()],
        ]], $user->id);
        $payments = $booking->payments;
        $this->assertTrue($payments->every('status', 'pending'));
        $this->actingAs($user);
        $this->patchJson(route('hotel-booking-payments.review', $payments[1]), ['status' => 'rejected'])
            ->assertUnprocessable()->assertJsonValidationErrors('verification_note');
        $this->patchJson(route('hotel-booking-payments.review', $payments[1]), ['status' => 'rejected', 'verification_note' => 'Transaction not found'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson(route('hotel-sales.verify-payments', $sale))->assertOk()->assertJsonPath('verified_count', 1);
        $first = $payments[0]->fresh();
        $this->assertSame('verified', $first->status);
        $this->assertSame($user->id, $first->verified_by);
        $this->assertNotNull($first->verified_at);
        $this->assertSame('rejected', $payments[1]->fresh()->status);
        $this->postJson(route('hotel-sales.verify-payments', $sale))->assertOk()->assertJsonPath('verified_count', 0);
        $totals = app(HotelBookingService::class)->totals($booking->fresh());
        $this->assertSame('3000.00', $totals['received']);
        $this->assertSame('7000.00', $totals['due']);
        $this->patchJson(route('hotel-booking-payments.update', $first), ['status' => 'verified'])->assertUnprocessable();
        $this->patchJson(route('hotel-booking-payments.update', $first), ['amount' => '1500'])
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.verified_by', null)->assertJsonPath('data.verified_at', null);
        $this->patchJson(route('hotel-booking-payments.review', $first), ['status' => 'verified'])
            ->assertOk()->assertJsonPath('data.status', 'verified');
        $this->patchJson(route('hotel-booking-payments.review', $first), ['status' => 'rejected', 'verification_note' => 'No'])
            ->assertUnprocessable();
        $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->assertSee('Verified')->assertSee('Rejected')->assertSee('Transaction not found');
    }

    public function test_payment_verification_requires_login_and_a_sold_sale(): void
    {
        $sale = $this->sale();
        $this->postJson(route('hotel-sales.verify-payments', $sale))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('hotel-sales.verify-payments', $sale))
            ->assertUnprocessable()->assertJsonValidationErrors('sale');
    }

    public function test_pending_sold_moves_to_sold_only_after_all_payments_are_verified(): void
    {
        $user = User::factory()->create();
        $sale = $this->sale();
        $booking = app(HotelBookingService::class)->sell($sale, ['rooms' => [[
            'hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $this->hotelAccountId(), 'room_name' => 'Room',
            'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22',
            'customer_price_per_night' => '5000', 'partner_price_per_night' => '4000',
        ]], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '3000']], 'payments' => [
            ['amount' => '1000', 'payment_method' => 'cash', 'paid_at' => now()],
            ['amount' => '2000', 'payment_method' => 'bank', 'paid_at' => now()],
        ]], $user->id);
        $this->actingAs($user);
        $this->get('/hotel/pending-sold')->assertOk()->assertSee('Pending Sold Tickets');
        $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->assertJsonPath('recordsTotal', 1);
        $this->getJson('/ship-tickets/data/sold')->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->get('/hotel-payouts/pending')->assertOk()->assertSee('Hotel Payment Method');
        $this->getJson('/hotel-payouts/pending/data')->assertOk()->assertJsonPath('recordsTotal', 0);
        $first = $booking->payments()->first();
        $this->patchJson(route('hotel-booking-payments.review', $first), ['status' => 'verified'])->assertOk();
        $this->getJson('/ship-tickets/data/pending-sold')->assertJsonPath('recordsTotal', 1);
        $this->postJson(route('hotel-sales.verify-payments', $sale))->assertOk();
        $this->getJson('/ship-tickets/data/pending-sold')->assertJsonPath('recordsTotal', 0);
        $this->getJson('/ship-tickets/data/sold')->assertJsonPath('recordsTotal', 1);
        $this->getJson('/hotel-payouts/pending/data')->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonStructure(['data' => [['hotel_payment_accounts', 'payment_details', 'sold_by']]]);
        $this->patchJson(route('hotel-booking-payments.update', $first), ['amount' => '1200'])->assertOk();
        $this->getJson('/ship-tickets/data/pending-sold')->assertJsonPath('recordsTotal', 1);
        $this->getJson('/ship-tickets/data/sold')->assertJsonPath('recordsTotal', 0);
    }

    private function hotelAccountId(): int
    {
        return \App\Models\HotelPaymentAccount::firstOrCreate(
            ['bookme_hotel_id' => 523],
            ['hotel_name' => 'Hotel', 'payment_method' => 'bank', 'account_name' => 'Hotel', 'account_number' => '001234', 'bank_name' => 'Bank', 'is_active' => true],
        )->id;
    }

    private function sale(): ShipTicketSale
    {
        return ShipTicketSale::create([
            'customer_name' => 'Customer', 'customer_mobile' => '0123456789',
            'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100,
            'issued_date' => '2026-10-14',
        ]);
    }

    public function test_other_fee_is_manually_recorded_and_validated(): void
    {
        $user = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password']);
        $sale = $this->sale();
        $payload = ['rooms' => [[
            'hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $this->hotelAccountId(), 'room_name' => 'Deluxe',
            'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-23',
            'customer_price_per_night' => '5000.00', 'partner_price_per_night' => '4000.00',
        ]], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '1000']], 'payments' => [[
            'amount' => '1000.00', 'other_fee' => '18.50', 'payment_method' => 'bkash', 'paid_at' => '2026-10-15 12:00:00',
        ]]];
        $this->actingAs($user);
        foreach (['-1', '1.234', 'invalid'] as $fee) {
            $invalid = $payload;
            $invalid['payments'][0]['other_fee'] = $fee;
            $this->postJson(route('hotel-sales.sell', $sale), $invalid)->assertUnprocessable()->assertJsonValidationErrors('payments.0.other_fee');
        }
        $this->postJson(route('hotel-sales.sell', $sale), $payload)->assertCreated()
            ->assertJsonPath('data.payments.0.other_fee', '18.50')
            ->assertJsonPath('data.totals.received', '1000.00')
            ->assertJsonPath('data.totals.due', '14000.00')
            ->assertJsonPath('data.totals.profit', '3000.00');
        $payment = $sale->hotelBookings()->first()->payments()->first();
        $this->patchJson(route('hotel-booking-payments.update', $payment), ['other_fee' => '20.25'])
            ->assertOk()->assertJsonPath('data.other_fee', '20.25');
        $details = $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->json('data.0.payment_details');
        $this->assertStringContainsString('Other Fee:', $details);
        $this->assertStringContainsString('20.25', $details);
    }

    public function test_sold_payment_details_include_all_payments_and_escape_user_content(): void
    {
        $user = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password']);
        $sale = $this->sale();
        app(HotelBookingService::class)->sell($sale, ['rooms' => [[
            'hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $this->hotelAccountId(), 'room_name' => 'Deluxe',
            'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-23',
            'customer_price_per_night' => '5000.00', 'partner_price_per_night' => '4000.00',
        ]], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '3000.75']], 'payments' => [
            ['amount' => '1000.25', 'payment_method' => 'cash', 'paid_at' => '2026-10-15 10:00:00', 'transaction_reference' => '<script>alert(1)</script>', 'remark' => 'Advance'],
            ['amount' => '2000.50', 'payment_method' => 'bkash', 'paid_at' => '2026-10-15 11:00:00', 'transaction_reference' => 'TX-123'],
        ]], $user->id);
        $details = $this->actingAs($user)->getJson('/ship-tickets/data/pending-sold')->assertOk()->json('data.0.payment_details');
        foreach (['Payment #1', 'Payment #2', '1000.25', '2000.50', '3000.75', '11999.25', 'bKash', 'TX-123', 'Advance', 'Staff', '15 Oct 2026, 10:00 AM'] as $value) {
            $this->assertStringContainsString($value, $details);
        }
        $this->assertStringContainsString('&lt;script&gt;', $details);
        $this->assertStringNotContainsString('<script>', $details);
        $this->get('/hotel/sold')->assertOk()->assertSee('Payment Details');
    }

    public function test_sale_calculates_nightly_totals_and_prevents_duplicate_sales(): void
    {
        $user = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password']);
        $sale = $this->sale();
        $data = ['rooms' => [[
            'hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $this->hotelAccountId(), 'room_name' => 'Deluxe',
            'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-23',
            'customer_price_per_night' => '5000.15', 'partner_price_per_night' => '4000.10',
        ]], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '2000.25']], 'payments' => [[
            'amount' => '2000.25', 'payment_method' => 'cash', 'paid_at' => '2026-10-14 12:00:00',
        ]]];
        $service = app(HotelBookingService::class);
        $booking = $service->sell($sale, $data, $user->id);
        $this->assertSame('sold', $sale->fresh()->hotel_status);
        $this->assertSame('13000.20', $service->totals($booking)['due']);
        $this->assertSame('3000.15', $service->totals($booking)['profit']);
        $this->assertSame(3, $booking->rooms->first()->nights);
        $this->assertSame(1, app(ShipTicketQueryService::class)->dashboard()['bookedTickets']);
        $this->expectException(ValidationException::class);
        $service->sell($sale, $data, $user->id);
    }

    public function test_feedback_service_preserves_creator_and_updates_dashboard(): void
    {
        $user = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password']);
        $sale = $this->sale();
        $service = app(HotelRemarkService::class);
        $service->store(['ticket_id' => $sale->id, 'remark_text' => 'Call customer'], $user->id);
        $service->update(['ticket_id' => $sale->id, 'remark_text' => 'Customer contacted']);
        $this->assertSame('feedbacked', $sale->fresh()->hotel_status);
        $this->assertSame($user->id, $sale->remark->added_by);
        $this->assertSame('Customer contacted', $sale->remark->remark_text);
        $this->assertSame(1, app(ShipTicketQueryService::class)->dashboard()['feedbackedTickets']);
    }

    public function test_feedbacked_ticket_can_be_booked_from_its_action_button(): void
    {
        $user = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password']);
        $sale = $this->sale();
        app(HotelRemarkService::class)->store(['ticket_id' => $sale->id, 'remark_text' => 'Interested'], $user->id);
        $this->actingAs($user)->getJson('/ship-tickets/data/feedbacked')->assertOk()->assertJsonCount(1, 'data');
        $data = $this->getJson('/ship-tickets/data/feedbacked')->json('data.0');
        $this->assertStringContainsString('Book Hotel', $data['action']);
        $this->assertStringContainsString('btn-sell', $data['action']);
        $this->postJson(route('hotel-sales.sell', $sale), ['rooms' => [[
            'hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $this->hotelAccountId(), 'room_name' => 'Deluxe',
            'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-23',
            'customer_price_per_night' => '5000.00', 'partner_price_per_night' => '4000.00',
        ]], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '0']], 'payments' => []])->assertCreated();
        $this->assertSame('sold', $sale->fresh()->hotel_status);
        $this->assertSame('Interested', $sale->remark->remark_text);
        $sold = $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->json('data.0');
        $this->assertStringNotContainsString('btn-sell', $sold['action']);
        $this->assertStringContainsString('Room: Deluxe', $sold['hotels']);
        $this->assertStringContainsString('Hotel', $sold['hotels']);
        $this->assertStringContainsString('20 Oct 2026', $sold['stay_dates']);
        $this->assertStringContainsString('23 Oct 2026', $sold['stay_dates']);
        $this->assertStringContainsString('No payments recorded.', $sold['payment_details']);
        $this->get('/hotel/sold')->assertOk()
            ->assertSee('Check-in / Check-out')
            ->assertDontSee('<th>Journey Date</th>', false)
            ->assertDontSee('<th>Rooms</th>', false);
    }
}
