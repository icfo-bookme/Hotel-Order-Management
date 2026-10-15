<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\HotelBookingPayment;
use App\Models\ShipTicketSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelPaymentService
{
    public function verifySale(ShipTicketSale $sale, int $userId): int
    {
        return DB::transaction(function () use ($sale, $userId) {
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->hotel_status !== 'sold') {
                throw ValidationException::withMessages(['sale' => 'Only sold bookings can be verified.']);
            }
            $bookingIds = $sale->hotelBookings()->where('status', 'confirmed')->lockForUpdate()->pluck('id');

            return HotelBookingPayment::whereIn('hotel_booking_id', $bookingIds)->where('status', 'pending')
                ->update(['status' => 'verified', 'verified_by' => $userId, 'verified_at' => now(), 'verification_note' => null]);
        });
    }

    public function review(HotelBookingPayment $payment, array $data, int $userId): HotelBookingPayment
    {
        return DB::transaction(function () use ($payment, $data, $userId) {
            HotelBooking::lockForUpdate()->findOrFail($payment->hotel_booking_id);
            $payment = HotelBookingPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'This payment has already been reviewed. Refresh the list.']);
            }
            $payment->status = $data['status'];
            $payment->verification_note = $data['verification_note'] ?? null;
            $payment->verified_by = $data['status'] === 'verified' ? $userId : null;
            $payment->verified_at = $data['status'] === 'verified' ? now() : null;
            $payment->save();

            return $payment;
        });
    }

    public function __construct(private readonly PaymentAttachmentService $attachments)
    {
    }

    public function create(array $data, int $userId): HotelBookingPayment
    {
        return $this->attachments->transaction(function ($upload) use ($data, $userId) {
            $booking = HotelBooking::lockForUpdate()->findOrFail($data['hotel_booking_id']);
            unset($data['hotel_booking_id']);
            $data['created_by'] = $userId;

            return $booking->payments()->create($upload($data));
        });
    }

    public function update(HotelBookingPayment $payment, array $data): HotelBookingPayment
    {
        return $this->attachments->transaction(function ($upload) use ($payment, $data) {
            $booking = HotelBooking::lockForUpdate()->findOrFail($payment->hotel_booking_id);
            if ($booking->payouts()->exists()) {
                throw ValidationException::withMessages(['payment' => 'Existing customer payments cannot be changed after a hotel payout.']);
            }
            $payment = HotelBookingPayment::lockForUpdate()->findOrFail($payment->id);
            unset($data['hotel_booking_id'], $data['created_by']);
            $oldPath = $payment->attachment_path;
            $data = $upload($data);
            if ($data !== []) {
                $payment->status = 'pending';
                $payment->verified_by = null;
                $payment->verified_at = null;
                $payment->verification_note = null;
            }
            $payment->update($data);
            if (isset($data['attachment_path']) && $oldPath !== $data['attachment_path']) {
                DB::afterCommit(fn () => $this->attachments->delete($oldPath));
            }

            return $payment;
        });
    }
}
