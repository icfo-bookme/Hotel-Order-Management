<?php

namespace Tests\Feature;

use App\Models\HotelPaymentAccount;
use App\Models\HotelPayout;
use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\HotelBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HotelPayoutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function booking(): \App\Models\HotelBooking
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $sale = ShipTicketSale::create(['customer_name' => 'Customer', 'customer_mobile' => '01712345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-15']);
        $rooms = [];
        foreach ([523 => 'Hotel A', 426 => 'Hotel B'] as $hotelId => $name) {
            $account = HotelPaymentAccount::create(['bookme_hotel_id' => $hotelId, 'hotel_name' => $name, 'payment_method' => 'bank', 'account_name' => $name, 'account_number' => '001234', 'bank_name' => 'Bank', 'is_active' => true, 'is_default' => true]);
            $room = ['bookme_hotel_id' => $hotelId, 'hotel_name' => $name, 'hotel_payment_account_id' => $account->id, 'room_name' => 'Deluxe', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => $hotelId === 523 ? '2500' : '4000', 'partner_price_per_night' => $hotelId === 523 ? '2000' : '3500'];
            $rooms[] = $room;
            if ($hotelId === 523) {
                $rooms[] = array_replace($room, ['room_name' => 'Couple']);
            }
        }

        return app(HotelBookingService::class)->sell($sale, [
            'rooms' => $rooms,
            'payments' => [['amount' => '7000', 'other_fee' => '150', 'payment_method' => 'bkash', 'paid_at' => now()]],
            'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '4000'], ['bookme_hotel_id' => 426, 'amount' => '3000']],
        ], $user->id);
    }

    public function test_hotel_advances_drive_separate_partial_and_completed_payouts(): void
    {
        $booking = $this->booking();
        $a = $booking->advances()->where('bookme_hotel_id', 523)->firstOrFail();
        $b = $booking->advances()->where('bookme_hotel_id', 426)->firstOrFail();
        $token = (string) Str::uuid();
        $payload = ['amount' => '1000', 'submission_id' => $token];
        $this->postJson(route('hotel-payouts.store', $a), $payload)->assertUnprocessable();
        $this->postJson(route('hotel-sales.verify-payments', $booking->sale))->assertOk();
        $pending = $this->getJson(route('hotel-payouts.pending.data'))->assertOk()->assertJsonPath('recordsTotal', 2)->json('data');
        $rowA = collect($pending)->firstWhere('bookme_hotel_id', 523);
        $this->assertIsArray($rowA['allocations']);
        $this->assertCount(2, $rowA['allocations']);
        $this->assertSame('4000.00', collect($rowA['allocations'])->firstWhere('bookme_hotel_id', 523)['amount']);
        $this->assertSame('2000.00', $rowA['payout_amount']);
        $this->assertSame('6000.00', $rowA['balance']['customer_due']);
        $this->assertStringContainsString('Couple', $rowA['hotels']);
        $this->assertStringContainsString('Deluxe', $rowA['hotels']);
        $this->postJson(route('hotel-payouts.store', $a), $payload)->assertCreated()->assertJsonPath('data.amount', '1000.00');
        $this->postJson(route('hotel-payouts.store', $a), $payload)->assertCreated();
        $this->assertDatabaseCount('hotel_payouts', 1);
        $this->postJson(route('hotel-payouts.store', $a), ['amount' => '1000.01', 'submission_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson(route('hotel-payouts.store', $b), $payload)->assertUnprocessable();
        $this->patchJson(route('hotel-bookings.advances', $booking), ['hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '2500'], ['bookme_hotel_id' => 426, 'amount' => '4500']]])->assertUnprocessable();
        $this->assertSame('4000.00', $a->fresh()->advance_amount);
        $this->postJson(route('hotel-payouts.store', $a), ['amount' => '1000', 'submission_id' => (string) Str::uuid()])->assertCreated();
        $this->getJson(route('hotel-payouts.pending.data'))->assertJsonPath('recordsTotal', 1);
        $this->getJson(route('hotel-payouts.paid.data'))->assertJsonPath('recordsTotal', 2);
        $this->postJson(route('hotel-payouts.store', $b), ['amount' => '2000', 'submission_id' => (string) Str::uuid()])->assertCreated();
        $this->getJson(route('hotel-payouts.pending.data'))->assertJsonPath('recordsTotal', 0);
        $this->getJson(route('hotel-payouts.paid.data'))->assertJsonPath('recordsTotal', 3);
        $this->get(route('hotel-payouts.paid'))->assertOk()->assertSee('Paid Payouts');
        $first = HotelPayout::firstOrFail();
        $first->paymentAccount->update(['account_number' => '999999']);
        $this->assertSame('001234', $first->fresh()->account_number);
        $this->patchJson(route('hotel-booking-payments.update', $booking->payments()->first()), ['amount' => '6000'])->assertUnprocessable();
        $this->patchJson(route('hotel-booking-rooms.update', $booking->rooms()->first()), ['partner_price_per_night' => '100'])->assertUnprocessable();
    }

    public function test_allocation_must_match_received_and_payout_requires_active_account_and_login(): void
    {
        $booking = $this->booking();
        $a = $booking->advances()->firstOrFail();
        $this->patchJson(route('hotel-bookings.advances', $booking), ['hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '100']]])->assertUnprocessable();
        $this->postJson(route('hotel-sales.verify-payments', $booking->sale))->assertOk();
        $booking->accounts()->first()->paymentAccount->update(['is_active' => false]);
        $this->postJson(route('hotel-payouts.store', $a), ['amount' => '1000', 'submission_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('hotel_payouts', 0);
        auth()->logout();
        $this->postJson(route('hotel-payouts.store', $a), ['amount' => '1000', 'submission_id' => (string) Str::uuid()])->assertUnauthorized();
    }

    public function test_sell_requires_hotel_ids_and_explicit_advances_even_for_one_hotel(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = HotelPaymentAccount::create(['bookme_hotel_id' => 523, 'hotel_name' => 'Hotel', 'payment_method' => 'bank', 'account_name' => 'Hotel', 'account_number' => '001234', 'bank_name' => 'Bank', 'is_active' => true]);
        $sale = ShipTicketSale::create(['customer_name' => 'Customer', 'customer_mobile' => '01712345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-15']);
        $room = ['hotel_name' => 'Hotel', 'hotel_payment_account_id' => $account->id, 'room_name' => 'Room', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => '5000', 'partner_price_per_night' => '4000'];
        $this->postJson(route('hotel-sales.sell', $sale), ['rooms' => [$room]])->assertUnprocessable()->assertJsonValidationErrors(['rooms.0.bookme_hotel_id', 'hotel_advances']);
        $room['bookme_hotel_id'] = 523;
        $this->postJson(route('hotel-sales.sell', $sale), ['rooms' => [$room], 'hotel_advances' => []])->assertUnprocessable()->assertJsonValidationErrors('hotel_advances');
        $this->assertDatabaseCount('hotel_bookings', 0);
        try {
            app(HotelBookingService::class)->sell($sale, ['rooms' => [$room]], $user->id);
            $this->fail('A missing allocation must not be inferred by the service.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('hotel_advances', $exception->errors());
        }
        $this->assertDatabaseCount('hotel_bookings', 0);
    }

    public function test_missing_account_does_not_hide_pending_payouts_and_new_sales_require_a_selection(): void
    {
        $booking = $this->booking();
        $booking->accounts()->delete();
        $this->postJson(route('hotel-sales.verify-payments', $booking->sale))->assertOk();
        $response = $this->getJson(route('hotel-payouts.pending.data'))->assertOk()->assertJsonPath('recordsTotal', 2);
        $this->assertStringContainsString('No payout payment method added for this hotel. Please add one.', $response->json('data.0.hotel_payment_accounts'));
        $this->assertStringContainsString('Add Payment Method', $response->json('data.0.hotel_payment_accounts'));
        $sale = ShipTicketSale::create(['customer_name' => 'Customer', 'customer_mobile' => '01712345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-15']);
        $room = ['hotel_name' => 'No Account Hotel', 'bookme_hotel_id' => 999, 'room_name' => 'Room', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => '5000', 'partner_price_per_night' => '4000'];
        $payload = ['rooms' => [$room], 'hotel_advances' => [['bookme_hotel_id' => 999, 'amount' => '0']]];
        $this->postJson(route('hotel-sales.sell', $sale), $payload)->assertUnprocessable()->assertJsonValidationErrors('rooms.0.hotel_payment_account_id');
        try {
            app(HotelBookingService::class)->sell($sale, $payload, auth()->id());
            $this->fail('A sale without a hotel payment account must fail.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame('Please select at least one payout payment method for each hotel.', $exception->errors()['rooms.0.hotel_payment_account_id'][0]);
        }
        $this->assertSame('pending', $sale->fresh()->hotel_status);
    }
}
