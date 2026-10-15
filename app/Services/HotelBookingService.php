<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\HotelBookingRoom;
use App\Support\Money;
use App\Models\ShipTicketSale;
use App\Models\HotelPaymentAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelBookingService
{
    public function __construct(private readonly PaymentAttachmentService $attachments)
    {
    }

    public function sell(ShipTicketSale $sale, array $data, int $userId): HotelBooking
    {
        return $this->attachments->transaction(function ($upload) use ($sale, $data, $userId) {
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($sale->id);
            if (!in_array($sale->hotel_status, ['pending', 'feedbacked'], true)) {
                throw ValidationException::withMessages(['sale' => 'This ticket is no longer available for hotel booking. Please refresh the list.']);
            }
            $booking = $sale->hotelBookings()->create([
                'status' => 'confirmed',
                'remark' => $data['remark'] ?? null,
                'created_by' => $userId,
            ]);
            $accounts = [];
            foreach ($data['rooms'] as $index => $room) {
                $hotelId = $room['bookme_hotel_id'] ?? null;
                $accountId = $room['hotel_payment_account_id'] ?? null;
                if (!$accountId) {
                    throw ValidationException::withMessages(['rooms.'.$index.'.hotel_payment_account_id' => 'Please select at least one payout payment method for each hotel.']);
                }
                $account = HotelPaymentAccount::whereKey($accountId)->where('bookme_hotel_id', $hotelId)->where('is_active', true)->lockForUpdate()->first();
                if (!$hotelId || !$account) {
                    throw ValidationException::withMessages(['rooms.'.$index.'.hotel_payment_account_id' => 'Select an active payment account belonging to this hotel.']);
                }
                if ($hotelId) {
                    if (array_key_exists($hotelId, $accounts) && $accounts[$hotelId] !== $accountId) {
                        throw ValidationException::withMessages(['rooms.'.$index.'.hotel_payment_account_id' => 'Use the same payment account for all rooms of this hotel.']);
                    }
                    $accounts[$hotelId] = $accountId;
                }
                unset($room['hotel_payment_account_id']);
                $booking->rooms()->create($room);
            }
            foreach ($accounts as $hotelId => $accountId) {
                $booking->accounts()->create(['bookme_hotel_id' => $hotelId, 'hotel_payment_account_id' => $accountId]);
            }
            foreach ($data['payments'] ?? [] as $payment) {
                $booking->payments()->create(array_merge($upload($payment), ['created_by' => $userId]));
            }
            if (!isset($data['hotel_advances'])) {
                throw ValidationException::withMessages(['hotel_advances' => 'Hotel advance allocations are required.']);
            }
            $this->allocateAdvances($booking, $data['hotel_advances']);
            $sale->update(['hotel_status' => 'sold']);

            return $booking;
        });
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return HotelBooking::with(['sale', 'rooms', 'payments', 'creator'])
            ->when(isset($filters['sales_id']), fn ($query) => $query->where('sales_id', $filters['sales_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->through(fn (HotelBooking $booking) => array_merge($booking->toArray(), ['totals' => $this->totals($booking)]));
    }

    public function create(array $data, int $userId): HotelBooking
    {
        return DB::transaction(function () use ($data, $userId) {
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($data['sales_id']);
            $booking = $sale->hotelBookings()->create([
                'status' => $data['status'] ?? 'pending',
                'remark' => $data['remark'] ?? null,
                'created_by' => $userId,
            ]);
            $this->syncSaleStatus($sale);

            return $booking;
        });
    }

    public function update(HotelBooking $booking, array $data): HotelBooking
    {
        return DB::transaction(function () use ($booking, $data) {
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($booking->sales_id);
            $booking = HotelBooking::lockForUpdate()->findOrFail($booking->id);
            if (isset($data['status']) && $data['status'] !== $booking->status && $booking->payouts()->exists()) {
                throw ValidationException::withMessages(['status' => 'Booking status cannot change after a hotel payout has been recorded.']);
            }
            $booking->update(array_intersect_key($data, array_flip(['status', 'remark'])));
            $this->syncSaleStatus($sale);

            return $booking;
        });
    }

    public function details(HotelBooking $booking): array
    {
        $booking->load(['sale', 'rooms', 'payments', 'creator', 'accounts.paymentAccount', 'advances']);

        return array_merge($booking->toArray(), ['totals' => $this->totals($booking)]);
    }

    public function totals(HotelBooking $booking): array
    {
        $booking->loadMissing(['rooms', 'payments']);
        $sell = $partner = $received = 0;
        foreach ($booking->rooms as $room) {
            $sell += Money::cents($room->customer_sell);
            $partner += Money::cents($room->partner_share);
        }
        foreach ($booking->payments as $payment) {
            $received += Money::cents($payment->amount);
        }

        return [
            'customer_sell' => Money::format($sell),
            'partner_share' => Money::format($partner),
            'profit' => Money::format($sell - $partner),
            'received' => Money::format($received),
            'due' => Money::format($sell - $received),
        ];
    }

    private function syncSaleStatus(ShipTicketSale $sale): void
    {
        if ($sale->hotelBookings()->where('status', 'confirmed')->exists()) {
            $sale->update(['hotel_status' => 'sold']);
        } elseif (in_array($sale->hotel_status, ['booked', 'sold'], true)) {
            $sale->update(['hotel_status' => $sale->remark()->exists() ? 'feedbacked' : 'pending']);
        }
    }

    public function allocateAdvances(HotelBooking $booking, array $allocations): void
    {
        $rooms = $booking->rooms()->get();
        if ($rooms->contains(fn ($room) => !$room->bookme_hotel_id)) {
            throw ValidationException::withMessages(['rooms' => 'Every room must have a Bookme hotel ID.']);
        }
        $hotels = $rooms->groupBy('bookme_hotel_id');
        $received = $booking->payments()->get()->sum(fn ($payment) => Money::cents($payment->amount));
        $input = collect($allocations);
        if ($input->count() !== $hotels->count() || $input->pluck('bookme_hotel_id')->unique()->count() !== $input->count()) {
            throw ValidationException::withMessages(['hotel_advances' => 'Enter the advance received for each hotel.']);
        }
        $total = 0;
        foreach ($hotels as $rooms) {
            $room = $rooms->first();
            $allocation = $input->firstWhere('bookme_hotel_id', $room->bookme_hotel_id);
            if (!$allocation || !array_key_exists('amount', $allocation)) {
                throw ValidationException::withMessages(['hotel_advances' => 'An explicit advance amount is required for every hotel.']);
            }
            $advance = Money::cents((string) $allocation['amount']);
            $sell = $rooms->sum(fn ($item) => Money::cents($item->customer_sell));
            if ($advance > $sell || $advance < 0) {
                throw ValidationException::withMessages(['hotel_advances' => 'Hotel advance must be between zero and the hotel customer total.']);
            }
            $booking->advances()->updateOrCreate(
                ['bookme_hotel_id' => $room->bookme_hotel_id, 'hotel_name' => $room->hotel_name],
                ['advance_amount' => Money::format($advance)],
            );
            $total += $advance;
        }
        if ($total !== $received || $input->contains(fn ($item) => !$hotels->has($item['bookme_hotel_id']))) {
            throw ValidationException::withMessages(['hotel_advances' => 'Hotel advances must match the payment amounts and selected hotels.']);
        }
    }

    public function createRoom(array $data): HotelBookingRoom
    {
        return DB::transaction(function () use ($data) {
            $booking = HotelBooking::lockForUpdate()->findOrFail($data['hotel_booking_id']);
            $this->ensureEditable($booking);
            unset($data['hotel_booking_id']);

            return $booking->rooms()->create($data);
        });
    }

    public function updateRoom(HotelBookingRoom $room, array $data): HotelBookingRoom
    {
        return DB::transaction(function () use ($room, $data) {
            $booking = HotelBooking::lockForUpdate()->findOrFail($room->hotel_booking_id);
            $this->ensureEditable($booking);
            $room = HotelBookingRoom::lockForUpdate()->findOrFail($room->id);
            unset($data['hotel_booking_id']);
            $room->update($data);

            return $room;
        });
    }

    private function ensureEditable(HotelBooking $booking): void
    {
        if ($booking->payouts()->exists()) {
            throw ValidationException::withMessages(['booking' => 'Rooms cannot be changed after a hotel payout has been recorded.']);
        }
    }
}
