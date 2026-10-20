<?php

namespace Tests\Feature;

use App\Models\HotelPaymentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HotelPaymentAccountTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['bookme_hotel_id' => 523, 'payment_method' => 'bank', 'account_name' => 'Hotel & Resort', 'account_number' => '00123456', 'bank_name' => 'Example Bank', 'branch_name' => 'Main', 'routing_number' => '001234567', 'is_default' => true, 'is_active' => true];
    }

    public function test_sale_does_not_require_or_save_a_payout_account(): void
    {
        $user = User::factory()->create();
        $sale = \App\Models\ShipTicketSale::create(['customer_name' => 'Customer', 'customer_mobile' => '0123456789', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-15']);
        $room = ['hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'room_name' => 'Deluxe', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => '5000', 'partner_price_per_night' => '4000'];
        $this->actingAsAdmin($user)->postJson(route('hotel-sales.sell', $sale), ['rooms' => [$room], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '0']]])->assertCreated()->assertJsonMissingPath('data.accounts');
        $this->assertDatabaseCount('hotel_booking_rooms', 1);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('hotel_booking_accounts'));
    }

    public function test_accounts_can_be_created_edited_and_listed_with_one_default_per_hotel(): void
    {
        Cache::forget('bookme.hotel-options.v3');
        Http::preventStrayRequests();
        Http::fake(['admin.bookme.com.bd/*' => Http::response([['id' => 523, 'name' => 'Ocean Breeze']])]);
        $user = User::factory()->create();
        $this->actingAsAdmin($user)->get(route('hotel-payment-accounts.index'))->assertOk()->assertSee('account-form');
        $firstId = $this->postJson(route('hotel-payment-accounts.store'), $this->payload())->assertCreated()
            ->assertJsonPath('data.hotel_name', 'Ocean Breeze')->assertJsonPath('data.created_by', $user->id)->json('data.id');
        $secondId = $this->postJson(route('hotel-payment-accounts.store'), array_replace($this->payload(), ['payment_method' => 'bkash', 'account_number' => '01712345678']))
            ->assertCreated()->assertJsonPath('data.bank_name', null)->json('data.id');
        $this->assertFalse(HotelPaymentAccount::findOrFail($firstId)->is_default);
        $update = $this->payload();
        unset($update['bookme_hotel_id']);
        $this->putJson(route('hotel-payment-accounts.update', $secondId), $update)->assertOk()->assertJsonPath('data.is_default', true);
        $this->putJson(route('hotel-payment-accounts.update', $firstId), $update)->assertOk()->assertJsonPath('data.is_default', true);
        $this->assertFalse(HotelPaymentAccount::findOrFail($secondId)->is_default);
        $this->assertSame(1, HotelPaymentAccount::where('is_default', true)->count());
        $update['is_active'] = false;
        $update['is_default'] = false;
        $this->putJson(route('hotel-payment-accounts.update', $firstId), $update)->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson(route('hotel-payment-accounts.data'))->assertOk()->assertJsonPath('recordsTotal', 2);
        $this->assertSame('00123456', HotelPaymentAccount::findOrFail($firstId)->account_number);
    }

    public function test_account_validation_rejects_invalid_hotels_and_inactive_defaults(): void
    {
        Cache::forget('bookme.hotel-options.v3');
        Http::fake(['admin.bookme.com.bd/*' => Http::sequence()->push([])->push([['id' => 523, 'name' => 'Hotel']])]);
        $this->postJson(route('hotel-payment-accounts.store'), $this->payload())->assertUnauthorized();
        $this->actingAsAdmin(User::factory()->create());
        $this->postJson(route('hotel-payment-accounts.store'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('bookme_hotel_id');
        $this->postJson(route('hotel-payment-accounts.store'), array_replace($this->payload(), ['bank_name' => '']))->assertUnprocessable()->assertJsonValidationErrors('bank_name');
        Cache::forget('bookme.hotel-options.v3');
        $this->postJson(route('hotel-payment-accounts.store'), array_replace($this->payload(), ['is_active' => false]))->assertUnprocessable()->assertJsonValidationErrors('is_default');
        $this->assertDatabaseCount('hotel_payment_accounts', 0);
    }
}
