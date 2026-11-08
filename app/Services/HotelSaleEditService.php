<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\ShipTicketSale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelSaleEditService
{
    public function __construct(private readonly HotelBookingService $bookings, private readonly PaymentAttachmentService $attachments) {}

    private function snapshot(HotelBooking $booking): array
    {
        $booking->load(['rooms.reschedules', 'removedRooms.reschedules', 'payments', 'advances', 'payouts']);
        return [
            'booking' => $booking->getAttributes(),
            'rooms' => $booking->rooms->sortBy('id')->map(fn ($room) => [
                ...$room->getAttributes(), 'reschedules' => $room->reschedules->sortBy('id')->map->getAttributes()->values()->all(),
            ])->values()->all(),
            'removed_rooms' => $booking->removedRooms->sortBy('id')->map->getAttributes()->values()->all(),
            'payments' => $booking->payments->sortBy('id')->map->getAttributes()->values()->all(),
            'advances' => $booking->advances->sortBy('id')->map->getAttributes()->values()->all(),
            'payouts' => $booking->payouts->sortBy('id')->map->getAttributes()->values()->all(),
        ];
    }

    public function details(HotelBooking $booking): array
    {
        $this->ensureSold($booking);
        $snapshot = $this->snapshot($booking);
        return [...$this->bookings->details($booking), 'version' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR))];
    }

    public function update(HotelBooking $booking, array $data, User $user): HotelBooking
    {
        return $this->attachments->transaction(function ($upload) use ($booking, $data, $user) {
            if ($booking->sales_id) ShipTicketSale::lockForUpdate()->findOrFail($booking->sales_id);
            $booking = HotelBooking::lockForUpdate()->findOrFail($booking->id);
            $this->ensureSold($booking);
            $before = $this->snapshot($booking);
            if (!hash_equals(hash('sha256', json_encode($before, JSON_THROW_ON_ERROR)), $data['version'])) {
                $this->invalid('version', 'This booking has changed. Close and reopen Edit Booking to load the latest information.');
            }
            $booking->update(array_intersect_key($data, array_flip(['customer_name', 'customer_mobile', 'whatsapp', 'reservation_number', 'remark'])));
            $this->rooms($booking, $data['rooms'], $user);
            $this->payments($booking, $data['payments'], $user, $upload);

            $hotelIds = $booking->rooms()->pluck('bookme_hotel_id');
            $booking->advances()->whereNotIn('bookme_hotel_id', $hotelIds)->delete();
            $this->bookings->allocateAdvances($booking, $data['hotel_advances']);
            $booking->load(['rooms.reschedules', 'payments', 'advances', 'payouts']);
            if (Money::cents($this->bookings->totals($booking)['due']) < 0) {
                $this->invalid('rooms', 'The booking total cannot be less than payments received. Adjust the payments or record the refund first.');
            }
            foreach ($booking->payouts->groupBy('bookme_hotel_id') as $hotelId => $payouts) {
                $paid = $payouts->sum(fn ($payout) => Money::cents($payout->amount));
                $rooms = $booking->rooms->where('bookme_hotel_id', $hotelId);
                $advance = $booking->advances->firstWhere('bookme_hotel_id', $hotelId);
                $profit = $rooms->sum(fn ($room) => Money::cents($room->customer_sell) - Money::cents($room->partner_share));
                if ($paid > 0 && (!$advance || $paid > Money::cents($advance->advance_amount) - $profit)) {
                    $this->invalid('hotel_advances', 'These changes would leave a hotel overpaid. Recover the hotel payment or adjust the prices and advance first.');
                }
            }
            $booking->touch();
            DB::table('hotel_booking_edits')->insert([
                'hotel_booking_id' => $booking->id, 'edited_by' => $user->id,
                'before' => json_encode($before, JSON_THROW_ON_ERROR),
                'after' => json_encode($this->snapshot($booking), JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            return $booking;
        });
    }

    private function rooms(HotelBooking $booking, array $input, User $user): void
    {
        $existing = $booking->rooms->keyBy('id');
        $kept = [];
        foreach ($input as $index => $data) {
            $id = $data['id'] ?? null;
            unset($data['id']);
            if ($id) {
                $room = $existing->get($id);
                if (!$room) $this->invalid('rooms.'.$index.'.id', 'This room does not belong to this booking.');
                if ($room->reschedules->isNotEmpty() && (string) $room->bookme_hotel_id !== (string) $data['bookme_hotel_id']) {
                    $this->invalid('rooms.'.$index.'.bookme_hotel_id', 'A room with reschedule fees must stay with its original hotel. Add a new room for a different hotel.');
                }
                $room->update($data);
            } else {
                $room = $booking->rooms()->create($data);
            }
            $kept[] = $room->id;
        }
        foreach ($existing->except($kept) as $room) {
            $room->forceFill(['removed_by' => $user->id, 'removal_reason' => 'Removed through Edit Booking'])->save();
            $room->delete();
        }
    }

    private function payments(HotelBooking $booking, array $input, User $user, callable $upload): void
    {
        $existing = $booking->payments->keyBy('id');
        $kept = [];
        foreach ($input as $index => $data) {
            $id = $data['id'] ?? null;
            $removeAttachment = $data['remove_attachment'] ?? false;
            $attachment = $data['attachment'] ?? null;
            unset($data['id'], $data['remove_attachment'], $data['attachment']);
            if ($id) {
                $payment = $existing->get($id);
                if (!$payment) $this->invalid('payments.'.$index.'.id', 'This payment does not belong to this booking.');
                if (!$payment->refunded_room_id && Money::cents((string) $data['amount']) <= 0) {
                    $this->invalid('payments.'.$index.'.amount', 'Payment amounts must be positive.');
                }
                $payment->fill($data);
                $changed = $payment->isDirty() || $attachment || ($removeAttachment && $payment->attachment_path);
                if ($payment->refunded_room_id && $changed) $this->invalid('payments', 'Recorded refunds cannot be changed.');
                if ($changed) {
                    abort_unless($user->can('payments.update'), 403, 'You do not have permission to edit payments.');
                    $payment->fill($upload(['attachment' => $attachment]));
                    if ($removeAttachment && !$attachment) $payment->attachment_path = null;
                    $payment->forceFill(['status' => 'pending', 'verified_by' => null, 'verified_at' => null, 'verification_note' => null])->save();
                }
            } else {
                abort_unless($user->can('payments.create'), 403, 'You do not have permission to add payments.');
                if (Money::cents((string) $data['amount']) <= 0) $this->invalid('payments.'.$index.'.amount', 'New payment amounts must be positive.');
                $payment = $booking->payments()->create([...$upload([...$data, 'attachment' => $attachment]), 'created_by' => $user->id]);
            }
            $kept[] = $payment->id;
        }
        foreach ($existing->except($kept) as $payment) {
            abort_unless($user->can('payments.update'), 403, 'You do not have permission to remove payments.');
            if ($payment->refunded_room_id) $this->invalid('payments', 'Recorded refunds cannot be removed.');
            $payment->delete();
        }
    }

    private function ensureSold(HotelBooking $booking): void
    {
        if ($booking->status !== 'confirmed') $this->invalid('booking', 'Only confirmed hotel sales can be edited.');
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
