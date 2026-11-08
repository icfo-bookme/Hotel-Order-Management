<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\ShipTicketSale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class HotelSalesTableService
{
    public function __construct(private readonly HotelBookingService $bookings) {}

    public function table(string $status, array $filters)
    {
        // Keep one row per ticket, including tickets with multiple hotel bookings.
        $tickets = ShipTicketSale::where('hotel_status', 'sold');
        $verified = ShipTicketSale::select('id')->withVerifiedHotelPayments();
        $status === 'sold' ? $tickets->whereIn('id', $verified) : $tickets->whereNotIn('id', $verified);
        $tickets->select('id', 'created_at')
            ->selectRaw('NULL AS direct_booking_id');
        foreach (['customer_name', 'customer_mobile', 'whatsapp'] as $field) {
            $value = $field === 'whatsapp'
                ? "COALESCE(hb.whatsapp, '')"
                : 'hb.'.$field;
            $tickets->selectRaw('COALESCE((SELECT '.$value.' FROM hotel_bookings hb WHERE hb.sales_id=ship_ticket_sales.id AND hb.status=? AND hb.deleted_at IS NULL ORDER BY hb.id DESC LIMIT 1), ship_ticket_sales.'.$field.') AS '.$field, ['confirmed']);
        }
        $direct = HotelBooking::whereNull('sales_id')->where('status', 'confirmed');
        $verifiedDirect = HotelBooking::select('id')->whereNull('sales_id')->withVerifiedPayments();
        $status === 'sold' ? $direct->whereIn('id', $verifiedDirect) : $direct->whereNotIn('id', $verifiedDirect);
        $direct->select('id', 'created_at')->selectRaw('id AS direct_booking_id')->addSelect('customer_name', 'customer_mobile', 'whatsapp');
        $rows = DB::query()->fromSub($tickets->toBase()->unionAll($direct->toBase()), 'hotel_sales');
        if (!empty($filters['date_from'])) $rows->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        if (!empty($filters['date_to'])) $rows->where('created_at', '<', Carbon::parse($filters['date_to'])->addDay()->startOfDay());

        $cache = [];
        $bookings = function ($row) use (&$cache) {
            $key = ($row->direct_booking_id ? 'D' : 'S').$row->id;
            return $cache[$key] ?? collect();
        };
        $url = fn ($row, $action) => $row->direct_booking_id
            ? url('/hotel-direct-bookings/'.$row->direct_booking_id.'/'.$action)
            : url('/hotel/sales/'.$row->id.'/'.$action);

        $table = DataTables::of($rows)
            ->editColumn('created_at', fn ($row) => Carbon::parse($row->created_at)->timezone(config('app.display_timezone'))->format('Y-m-d H:i:s'))
            ->addColumn('rooms_url', fn ($row) => $url($row, 'rooms'))
            ->addColumn('reschedules_url', fn ($row) => $url($row, 'reschedules'))
            ->addColumn('removable_rooms_url', fn ($row) => $url($row, 'removable-rooms'))
            ->addColumn('verify_url', fn ($row) => $url($row, 'verify-payments'))
            ->addColumn('hotels', fn ($row) => view('ship_tickets.partials.booking-cells', ['rooms' => $bookings($row)->flatMap->rooms, 'dates' => false])->render())
            ->addColumn('stay_dates', fn ($row) => view('ship_tickets.partials.booking-cells', ['rooms' => $bookings($row)->flatMap->rooms, 'dates' => true])->render())
            ->addColumn('sold_by', fn ($row) => $bookings($row)->pluck('creator.name')->filter()->unique()->implode(', ') ?: '-')
            ->addColumn('payment_details', fn ($row) => view('ship_tickets.partials.payment-details', [
                'dueLabel' => 'Due/Hotel Received',
                'bookings' => $bookings($row)->map(fn ($booking) => [
                    'id' => $booking->id, 'totals' => $this->bookings->totals($booking),
                    'payments' => $booking->payments->sortBy('paid_at')->values(), 'removed_rooms' => $booking->removedRooms,
                ]),
            ])->render())
            ->addColumn('action', fn ($row) => view('ship_tickets.partials.ticket-actions', [
                'bookings' => $bookings($row), 'status' => 'sold',
                'canVerify' => $bookings($row)->flatMap->payments->contains('status', 'pending'),
            ])->render())
            ->rawColumns(['hotels', 'stay_dates', 'payment_details', 'action'])
            ->escapeColumns(['customer_name', 'customer_mobile', 'whatsapp', 'sold_by']);

        $page = $table->prepareQuery()->results();
        $ticketIds = $page->whereNull('direct_booking_id')->pluck('id');
        $directIds = $page->whereNotNull('direct_booking_id')->pluck('direct_booking_id');
        $pageBookings = HotelBooking::where('status', 'confirmed')
            ->where(fn ($query) => $query->whereIn('sales_id', $ticketIds)->orWhereIn('id', $directIds))
            ->with(['rooms.reschedules', 'removedRooms', 'payments.creator', 'payments.verifier', 'creator'])->get();
        foreach ($pageBookings as $booking) {
            $key = $booking->sales_id ? 'S'.$booking->sales_id : 'D'.$booking->id;
            $cache[$key] ??= collect();
            $cache[$key]->push($booking);
        }
        return $table->toJson();
    }
}
