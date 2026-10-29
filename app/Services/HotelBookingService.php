<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\HotelBookingRoom;
use App\Support\Money;
use App\Models\ShipTicketSale;
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
            foreach ($data['rooms'] as $room) {
                $booking->rooms()->create($room);
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
        return HotelBooking::with(['sale', 'rooms.reschedules', 'payments', 'creator'])
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
        $booking->load(['sale', 'rooms.reschedules', 'removedRooms', 'payments', 'creator', 'advances']);

        return array_merge($booking->toArray(), ['totals' => $this->totals($booking)]);
    }

    public function totals(HotelBooking $booking): array
    {
        $booking->loadMissing(['rooms.reschedules', 'payments']);
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

    public function invoice(HotelBooking $booking): array
    {
        $booking->load(['sale', 'rooms.reschedules', 'payments', 'creator']);
        $totals = $this->totals($booking);
        $payments = $booking->payments->where('status', '!=', 'rejected')->sortBy('paid_at')->values();
        $received = $payments->sum(fn ($payment) => Money::cents($payment->amount));

        return [
            'booking' => $booking,
            'invoiceNumber' => 'BM-HB-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
            'payments' => $payments,
            'total' => $totals['customer_sell'],
            'received' => Money::format($received),
            'due' => Money::format(Money::cents($totals['customer_sell']) - $received),
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

    public function delete(HotelBooking $booking, string $reason, int $userId): void
    {
        DB::transaction(function () use ($booking, $reason, $userId) {
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($booking->sales_id);
            $booking = HotelBooking::lockForUpdate()->findOrFail($booking->id);
            if ($booking->payouts()->exists()) {
                throw ValidationException::withMessages(['booking' => 'This booking has a hotel payout and cannot be deleted.']);
            }
            $booking->deleted_by = $userId;
            $booking->delete_reason = $reason;
            $booking->save();
            $booking->delete();
            $this->syncSaleStatus($sale);
        });
    }

    public function restore(HotelBooking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($booking->sales_id);
            $booking = HotelBooking::onlyTrashed()->lockForUpdate()->findOrFail($booking->id);
            if ($sale->hotelBookings()->where('id', '>', $booking->id)->exists()
                || ($booking->status === 'confirmed' && $sale->hotelBookings()->where('status', 'confirmed')->exists())) {
                throw ValidationException::withMessages(['booking' => 'This ticket already has another booking. Resolve it before restoring this booking.']);
            }
            $booking->restore();
            $this->syncSaleStatus($sale);
        });
    }

    public function trash()
    {
        $bookings = HotelBooking::onlyTrashed()->with(['sale', 'rooms.reschedules', 'deletedBy'])->select('hotel_bookings.*');

        return \Yajra\DataTables\Facades\DataTables::eloquent($bookings)
            ->addColumn('customer', fn ($booking) => view('hotel_payments.partials.customer', ['sale' => $booking->sale])->render())
            ->filterColumn('customer', fn ($query, $keyword) => $query->whereHas('sale', fn ($sale) => $sale->where('customer_name', 'like', '%'.$keyword.'%')->orWhere('customer_mobile', 'like', '%'.$keyword.'%')))
            ->addColumn('hotels', fn ($booking) => view('ship_tickets.partials.booking-cells', ['rooms' => $booking->rooms, 'dates' => false, 'includeDates' => true])->render())
            ->addColumn('deleted_by_name', fn ($booking) => $booking->deletedBy?->name ?? '-')
            ->editColumn('deleted_at', fn ($booking) => $booking->deleted_at->timezone('Asia/Dhaka')->format('d M Y, h:i A'))
            ->addColumn('action', fn ($booking) => auth()->user()->can('bookings.restore') ? '<button type="button" data-restore-booking="'.$booking->id.'" title="Restore Booking" class="rounded-lg bg-blue-950 px-3 py-2 text-sm font-semibold text-white">Restore</button>' : '')
            ->rawColumns(['customer', 'hotels', 'action'])->escapeColumns(['delete_reason', 'deleted_by_name'])->toJson();
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

            if (array_intersect(['extra_bed_count', 'extra_bed_customer_rate', 'extra_bed_partner_rate'], array_keys($data))) {
                $booking->load(['rooms.reschedules', 'payments', 'advances']);
                if (Money::cents($this->totals($booking)['due']) < 0) {
                    throw ValidationException::withMessages(['extra_bed_count' => 'The new room total is below received payments. A refund adjustment is required.']);
                }
                foreach ($booking->advances as $advance) {
                    $hotelTotal = $booking->rooms->where('bookme_hotel_id', $advance->bookme_hotel_id)->sum(fn ($item) => Money::cents($item->customer_sell));
                    if (Money::cents($advance->advance_amount) > $hotelTotal) {
                        throw ValidationException::withMessages(['extra_bed_count' => 'The new hotel total is below its advance. Adjust the advance or refund first.']);
                    }
                }
            }

            return $room;
        });
    }

    private function ensureEditable(HotelBooking $booking): void
    {
        if ($booking->payouts()->exists()) {
            throw ValidationException::withMessages(['booking' => 'Rooms cannot be changed after a hotel payout has been recorded.']);
        }
    }

    public function rescheduleRooms(ShipTicketSale $shipTicketSale): \Illuminate\Support\Collection
    {
        abort_unless($shipTicketSale->hotel_status === 'sold', 422, 'Only hotel sales can be rescheduled.');
        $bookings = $shipTicketSale->hotelBookings()->where('status', 'confirmed')->with(['rooms.reschedules.creator', 'payouts'])->get();

        return $bookings->flatMap(fn ($booking) => $booking->rooms->map(fn ($room) => [
            'id' => $room->id,
            'hotel_name' => $room->hotel_name,
            'room_name' => $room->room_name,
            'check_in_date' => $room->check_in_date->format('Y-m-d'),
            'check_out_date' => $room->check_out_date->format('Y-m-d'),
            'customer_price_per_night' => $room->customer_price_per_night,
            'partner_price_per_night' => $room->partner_price_per_night,
            'extra_bed_count' => $room->extra_bed_count,
            'extra_bed_customer_rate' => $room->extra_bed_customer_rate,
            'extra_bed_partner_rate' => $room->extra_bed_partner_rate,
            'customer_sell' => $room->customer_sell,
            'partner_share' => $room->partner_share,
            'customer_fees' => $room->reschedules->sum(fn ($item) => Money::cents($item->customer_fee)),
            'partner_fees' => $room->reschedules->sum(fn ($item) => Money::cents($item->partner_fee)),
            'blocked' => $booking->payouts->isNotEmpty(),
            'history' => $room->reschedules->map(fn ($item) => [
                'from' => $item->old_check_in_date->format('Y-m-d').' / '.$item->old_check_out_date->format('Y-m-d'),
                'to' => $item->new_check_in_date->format('Y-m-d').' / '.$item->new_check_out_date->format('Y-m-d'),
                'customer_fee' => $item->customer_fee, 'partner_fee' => $item->partner_fee,
                'reason' => $item->reason, 'by' => $item->creator?->name,
            ]),
        ]))->values();
    }

    public function reschedule(HotelBookingRoom $room, array $data, int $userId): void
    {
        $this->attachments->transaction(function ($upload) use ($room, $data, $userId) {
            $booking = HotelBooking::findOrFail($room->hotel_booking_id);
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($booking->sales_id);
            $booking = HotelBooking::lockForUpdate()->findOrFail($booking->id);
            $this->ensureEditable($booking);
            if ($sale->hotel_status !== 'sold' || $booking->status !== 'confirmed') {
                throw ValidationException::withMessages(['booking' => 'Only confirmed hotel sales can be rescheduled.']);
            }
            $room = HotelBookingRoom::lockForUpdate()->findOrFail($room->id);
            $advance = $room->bookme_hotel_id
                ? $booking->advances()->where('bookme_hotel_id', $room->bookme_hotel_id)->lockForUpdate()->first()
                : null;
            if (isset($data['payment']) && !$advance) {
                throw ValidationException::withMessages(['payment' => 'This room needs a Bookme hotel ID and a hotel advance record before an additional payment can be recorded.']);
            }
            if ($room->check_in_date->format('Y-m-d') !== $data['old_check_in_date'] || $room->check_out_date->format('Y-m-d') !== $data['old_check_out_date']) {
                throw ValidationException::withMessages(['room' => 'Room dates have changed. Reopen the reschedule modal.']);
            }
            if ($data['old_check_in_date'] === $data['new_check_in_date'] && $data['old_check_out_date'] === $data['new_check_out_date']) {
                throw ValidationException::withMessages(['new_check_in_date' => 'Choose different stay dates to reschedule.']);
            }
            $room->reschedules()->create(array_merge(array_intersect_key($data, array_flip(['old_check_in_date', 'old_check_out_date', 'new_check_in_date', 'new_check_out_date', 'customer_fee', 'partner_fee', 'reason'])), ['created_by' => $userId]));
            $room->update(['check_in_date' => $data['new_check_in_date'], 'check_out_date' => $data['new_check_out_date']]);
            if (isset($data['payment'])) {
                $booking->payments()->create(array_merge($upload($data['payment']), ['created_by' => $userId, 'remark' => 'Reschedule: '.$data['reason']]));
                $advance->update(['advance_amount' => Money::format(Money::cents($advance->advance_amount) + Money::cents((string) $data['payment']['amount']))]);
            }
            $booking->load(['rooms.reschedules', 'payments', 'advances']);
            $totals = $this->totals($booking);
            if (Money::cents($totals['due']) < 0) {
                throw ValidationException::withMessages(['payment' => 'New booking total cannot be less than payments already received. A refund adjustment is required.']);
            }
            if ($advance) {
                $hotelTotal = $booking->rooms->where('bookme_hotel_id', $room->bookme_hotel_id)
                    ->sum(fn ($item) => Money::cents($item->customer_sell));
                if (Money::cents($advance->advance_amount) > $hotelTotal) {
                    throw ValidationException::withMessages(['payment' => 'The hotel advance cannot exceed the updated hotel customer total. A refund or allocation adjustment is required.']);
                }
            }
        });
    }
}
