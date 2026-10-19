<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\HotelPaymentAccount;
use App\Models\HotelPayout;
use App\Models\ShipTicketSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HotelReportTest extends TestCase
{
    use RefreshDatabase;

    private function booking(string $date = '2026-10-10 12:00:00', string $status = 'confirmed', int $hotelId = 523): HotelBooking
    {
        $user = auth()->user();
        $sale = ShipTicketSale::create(['customer_name' => '=2+2', 'customer_mobile' => '01712345678', 'whatsapp' => '01912345678', 'ship_id' => 1, 'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-10', 'hotel_status' => 'sold']);
        $booking = $sale->hotelBookings()->create(['status' => $status, 'created_by' => $user->id]);
        $booking->created_at = $date;
        $booking->save();
        $booking->rooms()->create(['bookme_hotel_id' => $hotelId, 'hotel_name' => 'Hotel '.$hotelId, 'room_name' => 'Deluxe', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => '5000.15', 'partner_price_per_night' => '4000.10']);
        foreach (['verified' => '3000.25'] as $paymentStatus => $amount) {
            $payment = $booking->payments()->create(['amount' => $amount, 'other_fee' => '500.00', 'payment_method' => 'cash', 'paid_at' => now(), 'created_by' => $user->id]);
            $payment->status = $paymentStatus;
            $payment->save();
        }
        $account = HotelPaymentAccount::create(['bookme_hotel_id' => $hotelId, 'hotel_name' => 'Hotel', 'payment_method' => 'bank', 'account_name' => 'Hotel', 'account_number' => '001234', 'bank_name' => 'Bank', 'is_active' => true]);
        HotelPayout::create(['submission_id' => (string) Str::uuid(), 'hotel_booking_id' => $booking->id, 'bookme_hotel_id' => $hotelId, 'hotel_payment_account_id' => $account->id, 'amount' => '1000.15', 'paid_at' => now(), 'payment_method' => 'bank', 'account_name' => 'Hotel', 'account_number' => '001234', 'created_by' => $user->id]);

        return $booking;
    }

    public function test_report_totals_use_verified_receipts_and_the_same_filters_as_the_table(): void
    {
        $this->actingAs(User::factory()->create());
        $booking = $this->booking();
        $this->booking(status: 'cancelled');
        $this->booking(date: '2026-09-10 12:00:00');
        $this->booking(hotelId: 426);
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'hotel_id' => 523, 'draw' => 1];
        $response = $this->getJson(route('reports.hotel-sales.data', $filters))->assertOk()->assertJsonPath('recordsTotal', 1);
        $response->assertJsonPath('summary', ['bookings' => 1, 'sell' => '10000.30', 'partner' => '8000.20', 'profit' => '2000.10', 'received' => '3000.25', 'due' => '7000.05', 'paid' => '1000.15']);
        $response->assertJsonPath('data.0.id', $booking->id)->assertJsonPath('data.0.verified', '3000.25')->assertJsonPath('data.0.due', '7000.05');
        $this->getJson(route('reports.hotel-sales.data', array_replace($filters, ['q' => 'not-found'])))->assertOk()->assertJsonPath('recordsTotal', 0)->assertJsonPath('summary.sell', '0.00');
        $this->get(route('reports.hotel-sales', $filters))->assertOk()->assertSee('Verified Received')->assertSee('Export All CSV')->assertSee('csvHtml5')->assertDontSee('excelHtml5')->assertSee('pdfHtml5')->assertDontSee('name="q"', false);
    }

    public function test_report_export_uses_matching_totals_and_escapes_spreadsheet_formulas(): void
    {
        $this->actingAs(User::factory()->create());
        $this->booking();
        $response = $this->get(route('reports.hotel-sales.export', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'q' => '01712345678']))->assertOk()->assertDownload('hotel-report-2026-10-01-to-2026-10-31.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringContainsString('3000.25', $csv);
        $this->assertStringContainsString('7000.05', $csv);
        $this->assertStringNotContainsString('999.99', $csv);
        $this->assertStringNotContainsString('500.00', $csv);
    }

    public function test_report_requires_login_and_valid_date_filters(): void
    {
        $this->getJson(route('reports.hotel-sales.data'))->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson(route('reports.hotel-sales.data', ['date_from' => '2026-10-20', 'date_to' => '2026-10-01']))->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->getJson(route('reports.hotel-sales.data', ['hotel_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors('hotel_id');
        $this->getJson(route('reports.hotel-sales.data'))->assertOk()->assertJsonPath('summary.bookings', 0);
    }

    public function test_unverified_sales_are_excluded_from_report_summary_table_and_export(): void
    {
        $this->actingAs(User::factory()->create());
        $verified = $this->booking();
        foreach (['pending', 'rejected'] as $status) {
            $booking = $this->booking();
            $booking->sale->update(['customer_name' => 'Exclude '.$status]);
            $payment = $booking->payments()->first();
            $payment->status = $status;
            $payment->save();
        }
        $mixed = $this->booking();
        $mixed->payments()->create(['amount' => '100', 'payment_method' => 'cash', 'paid_at' => now()]);
        $empty = $this->booking();
        $empty->payments()->delete();
        $unsold = $this->booking();
        $unsold->sale->update(['hotel_status' => 'pending']);
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
        $this->getJson(route('reports.hotel-sales.data', $filters))->assertOk()
            ->assertJsonPath('recordsTotal', 1)->assertJsonPath('summary.bookings', 1)
            ->assertJsonPath('data.0.id', $verified->id)->assertJsonPath('summary.received', '3000.25');
        $csv = $this->get(route('reports.hotel-sales.export', $filters))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Exclude pending', $csv);
        $this->assertStringNotContainsString('Exclude rejected', $csv);
        $this->assertCount(2, array_filter(explode("\n", trim($csv))));
    }

    public function test_native_datatable_search_updates_summary_for_all_matches_not_only_the_current_page(): void
    {
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 3; $i++) {
            $this->booking()->sale->update(['customer_name' => 'Needle Customer '.$i]);
        }
        $this->booking()->sale->update(['customer_name' => 'Other Customer']);
        $this->getJson(route('reports.hotel-sales.data', [
            'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'draw' => 7, 'start' => 0, 'length' => 1,
            'search' => ['value' => 'Needle', 'regex' => false],
            'columns' => [
                ['data' => 'id', 'name' => 'hotel_bookings.id', 'searchable' => 'false', 'orderable' => 'true'],
                ['data' => 'customer', 'name' => 'customer', 'searchable' => 'true', 'orderable' => 'false'],
            ],
            'order' => [['column' => 0, 'dir' => 'desc']],
        ]))->assertOk()->assertJsonPath('recordsTotal', 4)->assertJsonPath('recordsFiltered', 3)
            ->assertJsonCount(1, 'data')->assertJsonPath('summary.bookings', 3)->assertJsonPath('summary.sell', '30000.90')
            ->assertJsonPath('payment_methods.0.method', 'cash')->assertJsonPath('payment_methods.0.received', '9000.75')->assertJsonPath('payment_methods.0.count', 3);
    }

    public function test_payment_method_cards_and_filter_share_filters_without_duplicate_rooms_or_fees(): void
    {
        $this->actingAs(User::factory()->create());
        $booking = $this->booking();
        $booking->sale->update(['customer_name' => 'Card Customer']);
        $booking->rooms()->create(['bookme_hotel_id' => 523, 'hotel_name' => 'Hotel', 'room_name' => 'Second room', 'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'customer_price_per_night' => 500, 'partner_price_per_night' => 400]);
        $payment = $booking->payments()->create(['amount' => '200.35', 'other_fee' => 999, 'payment_method' => 'bkash', 'paid_at' => now(), 'transaction_reference' => '<script>bad</script>']);
        $payment->forceFill(['status' => 'verified'])->save();
        $pending = $this->booking();
        $pending->payments()->first()->forceFill(['status' => 'pending'])->save();
        $this->booking(hotelId: 426);
        $this->booking(date: '2026-09-01 10:00:00');
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'hotel_id' => 523, 'q' => 'Card Customer'];
        $result = $this->getJson(route('reports.hotel-sales.data', $filters))->assertOk()->assertJsonPath('summary.received', '3200.60')->assertJsonCount(6, 'payment_methods');
        $methods = collect($result->json('payment_methods'))->keyBy('method');
        $this->assertSame('3000.25', $methods['cash']['received']);
        $this->assertSame('200.35', $methods['bkash']['received']);
        $this->assertSame(1, $methods['bkash']['count']);
        $this->assertSame('0.00', $methods['bank']['received']);
        $bankOnly = $this->booking();
        $bankOnly->payments()->first()->forceFill(['payment_method' => 'bank'])->save();
        $methodFilters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'payment_method' => 'bkash'];
        $this->getJson(route('reports.hotel-sales.data', $methodFilters))->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.id', $booking->id)->assertJsonPath('summary.received', '3200.60');
        $this->getJson(route('reports.hotel-sales.data', array_replace($methodFilters, ['payment_method' => 'card'])))->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->getJson(route('reports.hotel-sales.data', array_replace($methodFilters, ['payment_method' => 'invalid'])))->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->get(route('reports.hotel-sales', $methodFilters))->assertOk()->assertSee('name="payment_method"', false)->assertDontSee('data-toggle-method');
        $csv = $this->get(route('reports.hotel-sales.export', $methodFilters))->assertOk()->streamedContent();
        $this->assertStringContainsString('Card Customer', $csv);
        $this->assertCount(2, array_filter(explode("\n", trim($csv))));
    }
}
