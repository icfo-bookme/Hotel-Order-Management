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

    public function test_payout_screenshot_upload_private_view_and_duplicate_retry(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $booking = $this->booking();
        $advance = $booking->advances()->where('bookme_hotel_id', 523)->firstOrFail();
        $this->postJson(route('hotel-sales.verify-payments', $booking->sale))->assertOk();
        $payload = ['hotel_payment_account_id' => $advance->paymentAccounts()->first()->id, 'amount' => '1000', 'submission_id' => (string) Str::uuid(), 'attachment' => \Illuminate\Http\UploadedFile::fake()->image('proof.png')];
        $response = $this->postJson(route('hotel-payouts.store', $advance), $payload)->assertCreated();
        $payout = HotelPayout::findOrFail($response->json('data.id'));
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($payout->attachment_path);
        $url = route('hotel-payouts.attachment', $payout);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson(route('hotel-payouts.paid.data'))->assertOk()->assertSee('data-view-payout-screenshot')->assertSee('hotel-payouts');
        $this->postJson(route('hotel-payouts.store', $advance), $payload)->assertCreated()->assertJsonPath('data.attachment_path', $payout->attachment_path);
        $this->assertDatabaseCount('hotel_payouts', 1);
        $this->assertCount(1, \Illuminate\Support\Facades\Storage::disk('local')->allFiles());
        \Illuminate\Support\Facades\Storage::disk('local')->delete($payout->attachment_path);
        $this->get($url)->assertNotFound();
        auth()->logout();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_payout_rejects_invalid_files_and_removes_upload_after_database_failure(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $booking = $this->booking();
        $advance = $booking->advances()->where('bookme_hotel_id', 523)->firstOrFail();
        $this->postJson(route('hotel-sales.verify-payments', $booking->sale))->assertOk();
        $payload = ['hotel_payment_account_id' => $advance->paymentAccounts()->first()->id, 'amount' => '1000', 'submission_id' => (string) Str::uuid()];
        $this->postJson(route('hotel-payouts.store', $advance), $payload + ['attachment' => \Illuminate\Http\UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('attachment');
        $this->postJson(route('hotel-payouts.store', $advance), $payload + ['attachment_path' => 'fake.png'])->assertUnprocessable()->assertJsonValidationErrors('attachment_path');
        $this->postJson(route('hotel-payouts.store', $advance), $payload + ['attachment' => \Illuminate\Http\UploadedFile::fake()->image('huge.png')->size(5121)])->assertUnprocessable()->assertJsonValidationErrors('attachment');
        HotelPayout::creating(function () { throw new \RuntimeException('Simulated save failure'); });
        try {
            app(\App\Services\HotelPayoutService::class)->create($advance, $payload + ['attachment' => \Illuminate\Http\UploadedFile::fake()->image('proof.png')], (int) auth()->id());
            $this->fail('Expected a failed payout save.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated save failure', $exception->getMessage());
        } finally {
            HotelPayout::flushEventListeners();
        }
        $this->assertDatabaseCount('hotel_payouts', 0);
        $this->assertCount(0, \Illuminate\Support\Facades\Storage::disk('local')->allFiles());
    }

    private function booking(): \App\Models\HotelBooking
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $sale = ShipTicketSale::create(['customer_name' => 'Customer', 'customer_mobile' => '01712345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-15']);
        $rooms = [];
        foreach ([523 => 'Hotel A', 426 => 'Hotel B'] as $hotelId => $name) {
            $account = HotelPaymentAccount::create(['bookme_hotel_id' => $hotelId, 'hotel_name' => $name, 'payment_method' => 'bank', 'account_name' => $name, 'account_number' => '001234', 'bank_name' => 'Bank', 'is_active' => true, 'is_default' => true]);
            $room = ['bookme_hotel_id' => $hotelId, 'hotel_name' => $name, 'room_name' => 'Deluxe', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => $hotelId === 523 ? '2500' : '4000', 'partner_price_per_night' => $hotelId === 523 ? '2000' : '3500'];
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
        $payload = ['hotel_payment_account_id' => $a->paymentAccounts()->first()->id, 'amount' => '1000', 'submission_id' => $token];
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
        $this->postJson(route('hotel-payouts.store', $a), ['hotel_payment_account_id' => $a->paymentAccounts()->first()->id, 'amount' => '1000.01', 'submission_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson(route('hotel-payouts.store', $b), $payload)->assertUnprocessable();
        $this->patchJson(route('hotel-bookings.advances', $booking), ['hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '2500'], ['bookme_hotel_id' => 426, 'amount' => '4500']]])->assertUnprocessable();
        $this->assertSame('4000.00', $a->fresh()->advance_amount);
        $this->postJson(route('hotel-payouts.store', $a), ['hotel_payment_account_id' => $a->paymentAccounts()->first()->id, 'amount' => '1000', 'submission_id' => (string) Str::uuid()])->assertCreated();
        $this->getJson(route('hotel-payouts.pending.data'))->assertJsonPath('recordsTotal', 1);
        $this->getJson(route('hotel-payouts.paid.data'))->assertJsonPath('recordsTotal', 2);
        $this->postJson(route('hotel-payouts.store', $b), ['hotel_payment_account_id' => $b->paymentAccounts()->first()->id, 'amount' => '2000', 'submission_id' => (string) Str::uuid()])->assertCreated();
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
        $a->paymentAccounts()->first()->update(['is_active' => false]);
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
        $room = ['hotel_name' => 'Hotel', 'room_name' => 'Room', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => '5000', 'partner_price_per_night' => '4000'];
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

    public function test_missing_accounts_do_not_hide_pending_payouts_or_block_sales(): void
    {
        $booking = $this->booking();
        HotelPaymentAccount::query()->update(['is_active' => false]);
        $this->postJson(route('hotel-sales.verify-payments', $booking->sale))->assertOk();
        $response = $this->getJson(route('hotel-payouts.pending.data'))->assertOk()->assertJsonPath('recordsTotal', 2);
        $this->assertSame([], $response->json('data.0.balance.accounts'));
        $this->assertNotEmpty($response->json('data.0.balance.reason'));
    }

    public function test_payout_lists_all_hotel_accounts_and_requires_an_explicit_valid_selection(): void
    {
        $booking = $this->booking();
        $advance = $booking->advances()->where('bookme_hotel_id', 523)->firstOrFail();
        $second = HotelPaymentAccount::create(['bookme_hotel_id' => 523, 'hotel_name' => 'Hotel A', 'payment_method' => 'bkash', 'account_name' => 'Second account', 'account_number' => '01799999999', 'is_active' => true]);
        $inactive = HotelPaymentAccount::create(['bookme_hotel_id' => 523, 'hotel_name' => 'Hotel A', 'payment_method' => 'nagad', 'account_name' => 'Inactive account', 'account_number' => '01899999999', 'is_active' => false]);
        $other = HotelPaymentAccount::where('bookme_hotel_id', 426)->firstOrFail();
        $this->postJson(route('hotel-sales.verify-payments', $booking->sale))->assertOk();
        $rows = $this->getJson(route('hotel-payouts.pending.data'))->assertOk()->json('data');
        $row = collect($rows)->firstWhere('bookme_hotel_id', 523);
        $this->assertCount(2, $row['balance']['accounts']);
        $this->assertStringContainsString('01799999999', $row['hotel_payment_accounts']);
        $this->assertStringContainsString('01899999999', $row['hotel_payment_accounts']);
        $payload = ['amount' => '1000', 'submission_id' => (string) Str::uuid()];
        $url = route('hotel-payouts.store', $advance);
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('hotel_payment_account_id');
        foreach ([$inactive->id, $other->id, 99999] as $id) {
            $this->postJson($url, $payload + ['hotel_payment_account_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('hotel_payment_account_id');
        }
        $this->postJson($url, $payload + ['hotel_payment_account_id' => $second->id])->assertCreated()->assertJsonPath('data.account_number', '01799999999');
        $this->postJson($url, $payload + ['hotel_payment_account_id' => $other->id])->assertUnprocessable();
        $second->update(['account_number' => '01700000000']);
        $paid = $this->getJson(route('hotel-payouts.paid.data'))->assertOk()->json('data.0.payment_account');
        $this->assertStringContainsString('Paid via', $paid);
        $this->assertStringContainsString('01799999999', $paid);
        $this->assertStringNotContainsString('01700000000', $paid);
        $this->assertStringNotContainsString('01899999999', $paid);
        $this->assertDatabaseCount('hotel_payouts', 1);
    }
}
