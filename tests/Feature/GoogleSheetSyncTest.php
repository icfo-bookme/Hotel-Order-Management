<?php

namespace Tests\Feature;

use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\GoogleSheetService;
use App\Services\HotelBookingService;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\Resource\Spreadsheets;
use Google\Service\Sheets\Resource\SpreadsheetsValues;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\ValueRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleSheetSyncTest extends TestCase
{
    use RefreshDatabase;

    private function sheetsService(array $header, array $ids, bool $missingTab = false): array
    {
        $sheets = new Sheets(new Client());
        $metadata = Mockery::mock(Spreadsheets::class);
        $values = Mockery::mock(SpreadsheetsValues::class);
        $sheets->spreadsheets = $metadata;
        $sheets->spreadsheets_values = $values;
        $metadata->shouldReceive('get')->once()->with('test-sheet', ['fields' => 'sheets.properties'])->andReturn(new Spreadsheet([
            'sheets' => $missingTab ? [] : [['properties' => ['sheetId' => 0, 'title' => "Hotel's Sales"]]],
        ]));
        if ($missingTab) {
            $metadata->shouldReceive('batchUpdate')->once()->with('test-sheet', Mockery::on(
                fn ($request) => $request->getRequests()[0]->getAddSheet()->getProperties()->getTitle() === "Hotel's Sales"
            ));
        }
        $values->shouldReceive('get')->once()->with('test-sheet', "'Hotel''s Sales'!A1:N1")->andReturn(new ValueRange(['values' => $header]));
        if (!$header || $header[0] === GoogleSheetService::HEADERS) {
            $values->shouldReceive('get')->once()->with('test-sheet', "'Hotel''s Sales'!A2:A")->andReturn(new ValueRange(['values' => $ids]));
        }
        $service = new class ($sheets) extends GoogleSheetService {
            public function __construct(private Sheets $sheets)
            {
            }
            protected function client(): Sheets
            {
                return $this->sheets;
            }
        };

        return [$service, $values];
    }

    public function test_sheet_append_uses_raw_values_and_skips_duplicate_booking_ids(): void
    {
        [$service, $values] = $this->sheetsService([GoogleSheetService::HEADERS], []);
        $row = ['5', '=untrusted customer', '01712345678'];
        $values->shouldReceive('append')->once()->with('test-sheet', "'Hotel''s Sales'!A:N", Mockery::on(fn ($body) => $body->getValues() === [$row]), ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']);
        $service->appendBooking('test-sheet', "Hotel's Sales", $row);
        [$service, $values] = $this->sheetsService([GoogleSheetService::HEADERS], [['5']]);
        $values->shouldNotReceive('append');
        $service->appendBooking('test-sheet', "Hotel's Sales", $row);
    }

    public function test_empty_tab_gets_headers_and_existing_headers_are_never_overwritten(): void
    {
        [$service, $values] = $this->sheetsService([], []);
        $values->shouldReceive('get')->once()->with('test-sheet', "'Hotel''s Sales'!A:N")->andReturn(new ValueRange());
        $values->shouldReceive('update')->once()->with('test-sheet', "'Hotel''s Sales'!A1:N1", Mockery::on(fn ($body) => $body->getValues() === [GoogleSheetService::HEADERS]), ['valueInputOption' => 'RAW']);
        $values->shouldReceive('append')->once();
        $service->appendBooking('test-sheet', "Hotel's Sales", ['5']);
        [$service, $values] = $this->sheetsService([['Existing business data']], []);
        $values->shouldNotReceive('append');
        $values->shouldNotReceive('update');
        $this->expectException(RuntimeException::class);
        $service->appendBooking('test-sheet', "Hotel's Sales", ['5']);
    }

    public function test_room_adjustment_replaces_the_existing_booking_row_without_appending(): void
    {
        [$service, $values] = $this->sheetsService([GoogleSheetService::HEADERS], [['2'], ['5']]);
        $row = ['5', 'Customer', '01712345678', '', 'Remaining room'];
        $values->shouldReceive('update')->once()->with('test-sheet', "'Hotel''s Sales'!A3:N3", Mockery::on(fn ($body) => $body->getValues() === [$row]), ['valueInputOption' => 'RAW']);
        $values->shouldNotReceive('append');
        $service->appendBooking('test-sheet', "Hotel's Sales", $row, true);
    }

    public function test_missing_daily_tab_is_created_before_headers_and_row(): void
    {
        [$service, $values] = $this->sheetsService([], [], true);
        $values->shouldReceive('get')->once()->with('test-sheet', "'Hotel''s Sales'!A:N")->andReturn(new ValueRange());
        $values->shouldReceive('update')->once();
        $values->shouldReceive('append')->once();
        $service->appendBooking('test-sheet', "Hotel's Sales", ['5']);
    }

    private function sell(bool $viaHttp = false): \App\Models\HotelBooking
    {
        $user = User::factory()->create();
        $sale = ShipTicketSale::create(['customer_name' => 'Customer', 'customer_mobile' => '01712345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-25']);
        $room = ['hotel_name' => 'Hotel One', 'bookme_hotel_id' => 523, 'room_name' => 'Deluxe', 'check_in_date' => '2026-10-26', 'check_out_date' => '2026-10-28', 'customer_price_per_night' => '5000', 'partner_price_per_night' => '4000'];
        $data = [
            'rooms' => [$room, array_merge($room, ['hotel_name' => 'Hotel Two', 'room_name' => 'Suite'])],
            'payments' => [['amount' => '3000', 'other_fee' => '100', 'payment_method' => 'bkash', 'paid_at' => now()]],
            'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '3000']],
        ];
        if ($viaHttp) {
            $response = $this->actingAsAdmin($user)->postJson(route('hotel-sales.sell', $sale), $data)->assertCreated();
            $this->syncResponse = $response;
            return \App\Models\HotelBooking::findOrFail($response->json('data.id'));
        }
        return app(HotelBookingService::class)->sell($sale, $data, $user->id);
    }

    private $syncResponse;

    public function test_sale_syncs_directly_to_daily_tab_before_response_without_queue(): void
    {
        config(['services.google_sheets.spreadsheet_id' => 'test-sheet']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-25 20:30:00', 'UTC'));
        $service = Mockery::mock(GoogleSheetService::class)->makePartial();
        $service->shouldReceive('appendBooking')->once()->with('test-sheet', '2026-10-26', Mockery::on(function ($row) {
            $this->assertDatabaseHas('hotel_bookings', ['id' => $row[0]]);
            $this->assertSame("Hotel One / Deluxe\nHotel Two / Suite", $row[4]);
            $this->assertSame(['20000.00', '16000.00', '4000.00', '3000.00', '17000.00'], array_slice($row, 6, 5));
            return true;
        }));
        $this->app->instance(GoogleSheetService::class, $service);
        $this->sell(true);
        $this->syncResponse->assertJsonPath('sheet_synced', true)->assertJsonPath('sheet_warning', null);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_sheet_failure_is_logged_and_reported_without_losing_saved_sale(): void
    {
        config(['services.google_sheets.spreadsheet_id' => 'test-sheet']);
        Log::spy();
        $service = Mockery::mock(GoogleSheetService::class)->makePartial();
        $service->shouldReceive('appendBooking')->once()->andThrow(new RuntimeException('Google unavailable'));
        $this->app->instance(GoogleSheetService::class, $service);
        $booking = $this->sell(true);
        $this->syncResponse->assertJsonPath('sheet_synced', false);
        $this->assertNotEmpty($this->syncResponse->json('sheet_warning'));
        $this->assertDatabaseHas('hotel_bookings', ['id' => $booking->id]);
        $this->assertSame('sold', $booking->sale->hotel_status);
        $this->assertDatabaseCount('jobs', 0);
        Log::shouldHaveReceived('error')->once();
    }
}
