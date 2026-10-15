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

    public function test_sale_saves_one_selected_account_per_hotel_and_rejects_invalid_accounts(): void
    {
        $user = User::factory()->create();
        $account = HotelPaymentAccount::create(array_merge($this->payload(), ['hotel_name' => 'Hotel', 'created_by' => $user->id]));
        $other = HotelPaymentAccount::create(array_merge($this->payload(), ['bookme_hotel_id' => 426, 'hotel_name' => 'Other Hotel', 'created_by' => $user->id]));
        $inactive = HotelPaymentAccount::create(array_merge($this->payload(), ['hotel_name' => 'Hotel', 'is_active' => false, 'is_default' => false]));
        $sale = \App\Models\ShipTicketSale::create(['customer_name' => 'Customer', 'customer_mobile' => '0123456789', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-15']);
        $room = ['hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $account->id, 'room_name' => 'Deluxe', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => '5000', 'partner_price_per_night' => '4000'];
        $this->actingAs($user);
        $this->getJson(route('hotel-payment-accounts.options', 523))->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', $account->id)->assertJsonPath('results.0.is_default', true);
        foreach ([$other->id, $inactive->id, null] as $invalidId) {
            $invalid = array_replace($room, ['hotel_payment_account_id' => $invalidId]);
            $this->postJson(route('hotel-sales.sell', $sale), ['rooms' => [$invalid], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '0']]])->assertUnprocessable();
        }
        $this->assertDatabaseCount('hotel_bookings', 0);
        $this->postJson(route('hotel-sales.sell', $sale), ['rooms' => [$room, array_replace($room, ['room_name' => 'Couple'])], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '0']]])
            ->assertCreated()->assertJsonCount(1, 'data.accounts')->assertJsonPath('data.accounts.0.hotel_payment_account_id', $account->id);
        $this->assertDatabaseCount('hotel_booking_accounts', 1);
        $this->assertDatabaseCount('hotel_booking_rooms', 2);
        $account->update(['is_default' => false]);
        $this->assertDatabaseHas('hotel_booking_accounts', ['hotel_payment_account_id' => $account->id, 'bookme_hotel_id' => 523]);
    }

    public function test_accounts_can_be_created_edited_and_listed_with_one_default_per_hotel(): void
    {
        Cache::forget('bookme.hotel-options.v3');
        Http::preventStrayRequests();
        Http::fake(['admin.bookme.com.bd/*' => Http::response([['id' => 523, 'name' => 'Ocean Breeze']])]);
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('hotel-payment-accounts.index'))->assertOk()->assertSee('account-form');
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
        $this->actingAs(User::factory()->create());
        $this->postJson(route('hotel-payment-accounts.store'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('bookme_hotel_id');
        $this->postJson(route('hotel-payment-accounts.store'), array_replace($this->payload(), ['bank_name' => '']))->assertUnprocessable()->assertJsonValidationErrors('bank_name');
        Cache::forget('bookme.hotel-options.v3');
        $this->postJson(route('hotel-payment-accounts.store'), array_replace($this->payload(), ['is_active' => false]))->assertUnprocessable()->assertJsonValidationErrors('is_default');
        $this->assertDatabaseCount('hotel_payment_accounts', 0);
    }
}
