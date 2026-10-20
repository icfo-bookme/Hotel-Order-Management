<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HotelOptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('bookme.hotel-options.v3');
        Cache::forget('bookme.room-options.v2.523');
        Cache::forget('bookme.room-options.v2.426');
        Http::preventStrayRequests();
    }

    public function test_hotels_are_normalized_and_cached_for_authenticated_staff(): void
    {
        Http::fake(['admin.bookme.com.bd/*' => Http::response([
            ['id' => 523, 'name' => 'Ocean Breeze', 'regular_price' => 6500],
            ['id' => 426, 'name' => 'Coral Moon'],
            ['id' => 427, 'name' => 'Ocean Breeze'],
            ['id' => 428, 'name' => ''],
            ['name' => 'Missing identifier'],
            ['id' => -1, 'name' => 'Invalid identifier'],
        ])]);

        $this->actingAsAdmin(User::factory()->create());
        $expected = ['results' => [
            ['id' => '426', 'text' => 'Coral Moon', 'hotel_id' => 426],
            ['id' => '523', 'text' => 'Ocean Breeze', 'hotel_id' => 523],
            ['id' => '427', 'text' => 'Ocean Breeze', 'hotel_id' => 427],
        ]];
        $this->getJson(route('hotel-options.index'))->assertOk()->assertExactJson($expected);
        $this->getJson(route('hotel-options.index'))->assertOk()->assertExactJson($expected);
        Http::assertSentCount(1);
    }

    public function test_failed_hotel_requests_can_be_retried(): void
    {
        Http::fake(['admin.bookme.com.bd/*' => Http::sequence()
            ->pushStatus(503)
            ->push(['error' => 'Unexpected response'])
            ->push([['id' => 1, 'name' => 'Hotel']])]);
        $this->actingAsAdmin(User::factory()->create());

        $this->getJson(route('hotel-options.index'))->assertStatus(502)->assertJsonStructure(['message']);
        $this->getJson(route('hotel-options.index'))->assertStatus(502);
        $this->getJson(route('hotel-options.index'))->assertOk()->assertJsonPath('results.0.text', 'Hotel');
        Http::assertSentCount(3);
    }

    public function test_guests_cannot_access_the_hotel_proxy(): void
    {
        $this->getJson(route('hotel-options.index'))->assertUnauthorized();
        $this->getJson(route('hotel-options.rooms', 523))->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_rooms_are_loaded_and_cached_separately_for_each_hotel(): void
    {
        Http::fake([
            'admin.bookme.com.bd/api/hotel/rooms/523' => Http::response([
                ['id' => 1710, 'room_name' => 'Sea View Couple Room', 'price_segments' => [['final_segment_price' => 10900]]],
                ['id' => 1711, 'room_name' => 'Deluxe Room'],
                ['id' => 1712, 'room_name' => ''],
            ]),
            'admin.bookme.com.bd/api/hotel/rooms/426' => Http::response([]),
        ]);
        $this->actingAsAdmin(User::factory()->create());
        $expected = ['results' => [
            ['id' => 'Deluxe Room', 'text' => 'Deluxe Room', 'customer_price_per_night' => null],
            ['id' => 'Sea View Couple Room', 'text' => 'Sea View Couple Room', 'customer_price_per_night' => 10900],
        ]];
        $this->getJson(route('hotel-options.rooms', 523))->assertOk()->assertExactJson($expected);
        $this->getJson(route('hotel-options.rooms', 523))->assertOk()->assertExactJson($expected);
        $this->getJson(route('hotel-options.rooms', 426))->assertOk()->assertExactJson(['results' => []]);
        Http::assertSentCount(2);
    }

    public function test_room_api_failure_is_not_cached_and_can_be_retried(): void
    {
        Http::fake(['admin.bookme.com.bd/*' => Http::sequence()
            ->pushStatus(503)->push(['error' => 'Invalid response'])->push([['room_name' => 'Deluxe']])]);
        $this->actingAsAdmin(User::factory()->create());
        $this->getJson(route('hotel-options.rooms', 523))->assertStatus(502);
        $this->getJson(route('hotel-options.rooms', 523))->assertStatus(502);
        $this->getJson(route('hotel-options.rooms', 523))->assertOk()->assertJsonPath('results.0.text', 'Deluxe');
        $this->getJson('/hotel-options/invalid/rooms')->assertNotFound();
        Http::assertSentCount(3);
    }
}
