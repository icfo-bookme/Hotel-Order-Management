<?php

namespace Tests\Feature;

use App\Models\ShipTicketSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_date_range_includes_the_entire_end_date_and_supports_one_sided_ranges(): void
    {
        $this->actingAsAdmin(User::factory()->create());

        foreach (['pending', 'feedbacked', 'sold'] as $status) {
            $ids = [];
            foreach (['2026-10-09 23:59:59', '2026-10-10 00:00:00', '2026-10-11 23:59:59', '2026-10-12 00:00:00'] as $date) {
                $sale = ShipTicketSale::create([
                    'customer_name' => 'Customer', 'customer_mobile' => '01712345678',
                    'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100,
                    'issued_date' => '2026-10-01', 'hotel_status' => $status,
                ]);
                $sale->created_at = $date;
                $sale->save();
                $ids[] = $sale->id;
            }
            $pageStatus = $status === 'sold' ? 'pending-sold' : $status;
            $response = $this->getJson(route('ship-tickets.data', [
                'status' => $pageStatus, 'date_from' => '2026-10-10', 'date_to' => '2026-10-11',
            ]))->assertOk();
            $this->assertEqualsCanonicalizing([$ids[1], $ids[2]], array_column($response->json('data'), 'id'));
            $this->getJson(route('ship-tickets.data', ['status' => $pageStatus, 'date_to' => '2026-10-09']))
                ->assertOk()->assertJsonCount(1, 'data');
            $this->getJson(route('ship-tickets.data', ['status' => $pageStatus, 'date_from' => '2026-10-12']))
                ->assertOk()->assertJsonCount(1, 'data');
            $this->getJson(route('ship-tickets.data', ['status' => $pageStatus]))
                ->assertOk()->assertJsonCount(4, 'data');
        }
    }

    public function test_invalid_dates_and_reversed_ranges_are_rejected(): void
    {
        $this->actingAsAdmin(User::factory()->create());
        $this->getJson(route('ship-tickets.data', ['status' => 'pending', 'date_from' => '2026-02-30']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_from');
        $this->getJson(route('ship-tickets.data', ['status' => 'feedbacked', 'date_from' => '2026-10-12', 'date_to' => '2026-10-10']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }
}
