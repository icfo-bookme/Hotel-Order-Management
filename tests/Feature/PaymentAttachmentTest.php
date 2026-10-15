<?php

namespace Tests\Feature;

use App\Models\HotelBooking;
use App\Models\ShipTicketSale;
use App\Models\User;
use App\Services\HotelBookingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentAttachmentTest extends TestCase
{
    use DatabaseMigrations;

    private function hotelAccountId(): int
    {
        return \App\Models\HotelPaymentAccount::firstOrCreate(
            ['bookme_hotel_id' => 523],
            ['hotel_name' => 'Hotel', 'payment_method' => 'bank', 'account_name' => 'Hotel', 'account_number' => '001234', 'bank_name' => 'Bank', 'is_active' => true],
        )->id;
    }

    private function screenshot(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    private function payload(): array
    {
        return ['rooms' => [[
            'hotel_name' => 'Hotel', 'bookme_hotel_id' => 523, 'hotel_payment_account_id' => $this->hotelAccountId(), 'room_name' => 'Room',
            'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-23',
            'customer_price_per_night' => '5000.00', 'partner_price_per_night' => '4000.00',
        ]], 'hotel_advances' => [['bookme_hotel_id' => 523, 'amount' => '1000']], 'payments' => [[
            'amount' => '1000.00', 'other_fee' => '10.00', 'payment_method' => 'bkash',
            'paid_at' => '2026-10-15 12:00:00', 'attachment' => $this->screenshot(),
        ]]];
    }

    private function sale(): ShipTicketSale
    {
        Storage::fake('local');
        $this->actingAs(User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password']));

        return ShipTicketSale::create([
            'customer_name' => 'Customer', 'customer_mobile' => '0123456789', 'ship_id' => 1,
            'ticket_fee' => 100, 'received_amount' => 100, 'issued_date' => '2026-10-15',
        ]);
    }

    public function test_screenshot_upload_view_and_replacement(): void
    {
        $sale = $this->sale();
        $this->post(route('hotel-sales.sell', $sale), $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $payment = $sale->hotelBookings()->firstOrFail()->payments()->firstOrFail();
        $oldPath = $payment->attachment_path;
        Storage::disk('local')->assertExists($oldPath);
        $this->get(route('hotel-booking-payments.attachment', $payment))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('View Screenshot', $this->getJson('/ship-tickets/data/pending-sold')->json('data.0.payment_details'));
        $sold = $this->getJson('/ship-tickets/data/pending-sold')->assertOk()->json('data.0');
        $this->assertSame('Staff', $sold['sold_by']);
        $this->assertStringContainsString('Other Fee:', $sold['payment_details']);
        $this->assertStringContainsString('10.00', $sold['payment_details']);
        $this->assertStringContainsString('data-view-payment-screenshot=', $sold['payment_details']);
        $this->assertStringContainsString('<img src=', $sold['payment_details']);
        $this->get('/hotel/sold')->assertOk()->assertSee('Sold By')->assertSee('id="payment-screenshot-modal"', false);
        $this->post(route('hotel-booking-payments.update', $payment), ['_method' => 'PATCH', 'attachment' => $this->screenshot()], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($payment->fresh()->attachment_path);
        $this->post('/logout');
        $this->get(route('hotel-booking-payments.attachment', $payment))->assertRedirect('/login');
    }

    public function test_invalid_files_are_rejected_without_creating_bookings(): void
    {
        $sale = $this->sale();
        foreach ([UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'), UploadedFile::fake()->create('large.png', 5121, 'image/png')] as $file) {
            $data = $this->payload();
            $data['payments'][0]['attachment'] = $file;
            $this->post(route('hotel-sales.sell', $sale), $data, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('payments.0.attachment');
        }
        $this->assertSame(0, HotelBooking::count());
        $this->assertSame([], Storage::disk('local')->allFiles('payment-attachments'));
    }

    public function test_failed_sale_removes_uploaded_files(): void
    {
        $sale = $this->sale();
        $data = $this->payload();
        $data['payments'][] = ['amount' => null, 'payment_method' => 'cash', 'paid_at' => '2026-10-15 12:00:00'];
        try {
            app(HotelBookingService::class)->sell($sale, $data, auth()->id());
            $this->fail('Invalid payment should roll back the sale.');
        } catch (\Illuminate\Database\QueryException $expected) {
            $this->assertSame('pending', $sale->fresh()->hotel_status);
            $this->assertSame(0, HotelBooking::count());
            $this->assertSame([], Storage::disk('local')->allFiles('payment-attachments'));
        }
    }
}
