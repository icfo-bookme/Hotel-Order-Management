<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\HotelBookingRoom;
use App\Models\ShipTicketSale;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HotelRoomRemovalService
{
    public function rooms(ShipTicketSale $sale): array
    {
        abort_unless($sale->hotel_status === 'sold', 422, 'Only hotel sales have rooms to remove.');
        return $sale->hotelBookings()->where('status', 'confirmed')
            ->with(['rooms.reschedules', 'payments', 'advances', 'payouts'])->get()
            ->flatMap(fn ($booking) => $booking->rooms->map(function ($room) use ($booking) {
                $advance = $booking->advances->firstWhere('bookme_hotel_id', $room->bookme_hotel_id);
                $hotelRooms = $booking->rooms->where('bookme_hotel_id', $room->bookme_hotel_id);
                $remainingSell = $hotelRooms->where('id', '!=', $room->id)->sum(fn ($item) => Money::cents($item->customer_sell));
                $remainingPartner = $hotelRooms->where('id', '!=', $room->id)->sum(fn ($item) => Money::cents($item->partner_share));
                $advanceCents = Money::cents($advance?->advance_amount ?? '0');
                $paid = $booking->payouts->where('bookme_hotel_id', $room->bookme_hotel_id)->sum(fn ($item) => Money::cents($item->amount));
                return [
                    'id' => $room->id, 'hotel_name' => $room->hotel_name, 'room_name' => $room->room_name,
                    'customer_sell' => $room->customer_sell, 'partner_share' => $room->partner_share,
                    'remaining_sell' => Money::format($remainingSell), 'remaining_partner' => Money::format($remainingPartner),
                    'advance' => Money::format($advanceCents), 'hotel_paid' => Money::format($paid),
                    'suggested_refund' => Money::format(max(0, $advanceCents - $remainingSell)),
                    'last_room' => $booking->rooms->count() <= 1,
                ];
            }))->values()->all();
    }

    public function remove(HotelBookingRoom $room, array $data, int $userId): HotelBooking
    {
        return DB::transaction(function () use ($room, $data, $userId) {
            $booking = HotelBooking::findOrFail($room->hotel_booking_id);
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($booking->sales_id);
            $booking = HotelBooking::lockForUpdate()->findOrFail($booking->id);
            $room = $booking->rooms()->lockForUpdate()->findOrFail($room->id);
            if ($sale->hotel_status !== 'sold' || $booking->status !== 'confirmed') {
                $this->invalid('room', 'Only confirmed hotel sale rooms can be removed.');
            }
            $booking->load(['rooms.reschedules', 'payments', 'advances', 'payouts']);
            if ($booking->rooms->count() <= 1) {
                $this->invalid('room', 'Keep at least one room in the booking. This option removes an extra room.');
            }
            $refund = Money::cents((string) $data['refund_amount']);
            $hotelRefund = Money::cents((string) $data['hotel_refund_amount']);
            $advance = $booking->advances()->where('bookme_hotel_id', $room->bookme_hotel_id)->lockForUpdate()->first();
            $received = $booking->payments->sum(fn ($payment) => Money::cents($payment->amount));
            $allocated = $booking->advances->sum(fn ($item) => Money::cents($item->advance_amount));
            if (!$advance || $received !== $allocated) {
                $this->invalid('room', 'Set hotel advances to match received payments before removing a room.');
            }
            $verified = $booking->payments->where('status', 'verified')->sum(fn ($payment) => Money::cents($payment->amount));
            if ($refund > 0 && $booking->payments->contains(fn ($payment) => $payment->status !== 'verified')) {
                $this->invalid('refund_amount', 'Resolve customer payment verification before recording a refund.');
            }
            if ($refund < 0 || $refund > Money::cents($room->customer_sell) || $refund > Money::cents($advance->advance_amount) || ($refund > 0 && $refund > $verified)) {
                $this->invalid('refund_amount', 'Refund cannot exceed this room total, its hotel advance, or verified funds received.');
            }
            $remainingRooms = $booking->rooms->where('id', '!=', $room->id);
            $hotelRooms = $remainingRooms->where('bookme_hotel_id', $room->bookme_hotel_id);
            $sell = $hotelRooms->sum(fn ($item) => Money::cents($item->customer_sell));
            $partner = $hotelRooms->sum(fn ($item) => Money::cents($item->partner_share));
            $newAdvance = Money::cents($advance->advance_amount) - $refund;
            if ($newAdvance > $sell) {
                $this->invalid('refund_amount', 'Refund at least '.Money::format($newAdvance - $sell + $refund).' so the remaining hotel advance does not exceed its room total.');
            }
            $payouts = $booking->payouts->where('bookme_hotel_id', $room->bookme_hotel_id);
            $paid = $payouts->sum(fn ($item) => Money::cents($item->amount));
            if ($hotelRefund < 0 || $hotelRefund > $paid || $hotelRefund > Money::cents($room->partner_share)) {
                $this->invalid('hotel_refund_amount', 'Hotel refund cannot exceed the net hotel payments or this room partner total.');
            }
            $minimumRecovery = max(0, $paid - max(0, $newAdvance - ($sell - $partner)));
            if ($hotelRefund < $minimumRecovery) {
                $this->invalid('hotel_refund_amount', 'Receive at least '.Money::format($minimumRecovery).' back from the hotel before removing this room.');
            }
            $remark = 'Room refund #'.$room->id.' ('.$room->hotel_name.' / '.$room->room_name.'): '.$data['reason'];
            if ($refund > 0) {
                $payment = $booking->payments()->make([
                    'amount' => Money::format(-$refund), 'other_fee' => '0.00',
                    'payment_method' => $data['payment_method'], 'paid_at' => now(),
                    'transaction_reference' => $data['transaction_reference'] ?? null,
                    'remark' => $remark, 'created_by' => $userId,
                ]);
                $payment->forceFill(['refunded_room_id' => $room->id, 'status' => 'verified', 'verified_by' => $userId, 'verified_at' => now()])->save();
            }
            if ($hotelRefund > 0) {
                // Reverse the actual payout using its account snapshot; never rewrite a paid transaction.
                $original = $payouts->first(fn ($item) => Money::cents($item->amount) > 0);
                $recovery = $booking->payouts()->make(array_merge($original->only([
                    'bookme_hotel_id', 'hotel_payment_account_id', 'payment_method', 'account_name',
                    'account_number', 'bank_name', 'branch_name', 'routing_number',
                ]), [
                    'submission_id' => (string) Str::uuid(), 'amount' => Money::format(-$hotelRefund),
                    'paid_at' => now(), 'created_by' => $userId, 'remark' => $remark,
                    'transaction_reference' => $data['transaction_reference'] ?? null,
                ]));
                $recovery->forceFill(['refunded_room_id' => $room->id])->save();
            }
            if ($hotelRooms->isEmpty()) {
                $advance->delete();
            } else {
                $advance->update(['advance_amount' => Money::format($newAdvance)]);
            }
            $room->forceFill(['removed_by' => $userId, 'removal_reason' => $data['reason']])->save();
            $room->delete();

            return $booking->fresh(['rooms.reschedules', 'payments', 'advances', 'payouts']);
        });
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
