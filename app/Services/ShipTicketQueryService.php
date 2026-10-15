<?php

namespace App\Services;

use App\Models\HotelRemark;
use App\Models\ShipTicketSale;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ShipTicketQueryService
{
    public function __construct(private readonly HotelBookingService $bookings)
    {
    }

    // Server-side data
    public function table(string $status)
    {
        $status = strtolower($status);
        $isSale = in_array($status, ['pending-sold', 'sold'], true);

        $tickets = ShipTicketSale::with('remark.user')->select([
            'id',
            'customer_name',
            'customer_mobile',
            'whatsapp',
            'journey_date',
            'hotel_status',
        ])->where('hotel_status', $isSale ? 'sold' : $status);
        if ($isSale) {
            $verifiedSales = ShipTicketSale::query()->select('id')->withVerifiedHotelPayments();
            if ($status === 'sold') {
                $tickets->whereIn('id', $verifiedSales);
            } else {
                $tickets->whereNotIn('id', $verifiedSales);
            }
        }

        if ($isSale) {
            $tickets->with(['hotelBookings' => fn ($query) => $query->where('status', 'confirmed')->with(['rooms', 'payments.creator', 'payments.verifier', 'creator'])]);
        }

        return DataTables::of($tickets)
            ->editColumn('journey_date', function ($row) {
                return $row->journey_date?->format('Y-m-d');
            })
            ->addColumn('feedback_by', function ($row) {
                // return user name if remark exists, otherwise '-'
                return $row->remark && $row->remark->user ? $row->remark->user->name : '-';
            })
            ->addColumn('remark_text', function ($row) {
                return $row->remark ? $row->remark->remark_text : '-';
            })
            ->addColumn('hotels', fn ($row) => $isSale ? view('ship_tickets.partials.booking-cells', ['rooms' => $row->hotelBookings->flatMap->rooms, 'dates' => false])->render() : '')
            ->addColumn('stay_dates', fn ($row) => $isSale ? view('ship_tickets.partials.booking-cells', ['rooms' => $row->hotelBookings->flatMap->rooms, 'dates' => true])->render() : '')
            ->addColumn('sold_by', fn ($row) => $isSale ? ($row->hotelBookings->pluck('creator.name')->filter()->unique()->implode(', ') ?: '-') : '')
            ->addColumn('payment_details', fn ($row) => $isSale ? view('ship_tickets.partials.payment-details', [
                'dueLabel' => 'Due/Hotel Received',
                'bookings' => $row->hotelBookings->map(fn ($booking) => [
                    'id' => $booking->id,
                    'totals' => $this->bookings->totals($booking),
                    'payments' => $booking->payments->sortBy('paid_at')->values(),
                ]),
            ])->render() : '')
            ->addColumn('action', fn ($row) => view('ship_tickets.partials.ticket-actions', ['status' => $row->hotel_status, 'canVerify' => $isSale && $row->hotelBookings->flatMap->payments->contains('status', 'pending')])->render())
            // Action contains server-generated HTML buttons, so keep it raw,
            // but escape every user-controlled column to prevent stored XSS.
            ->rawColumns(['action', 'hotels', 'stay_dates', 'payment_details'])
            ->escapeColumns([
                'customer_name',
                'customer_mobile',
                'whatsapp',
                'journey_date',
                'feedback_by',
                'remark_text',
                'sold_by',
            ])
            ->make(true);
    }

    public function dashboard(): array
    {
        $totalTickets = ShipTicketSale::count();
        $pendingTickets = ShipTicketSale::where('hotel_status', 'pending')->count();
        $feedbackedTickets = ShipTicketSale::where('hotel_status', 'feedbacked')->count();
        $bookedTickets = ShipTicketSale::whereIn('hotel_status', ['booked', 'sold'])->count();

        // Latest tickets
        $latestTickets = ShipTicketSale::with('remark.user')
            ->orderBy('journey_date', 'desc')
            ->take(5)
            ->get();

        // Remarks grouped by user and date
        $remarksByDate = HotelRemark::with('user', 'ticket')
            ->select(
                'added_by',
                DB::raw('DATE(created_at) as remark_date'),
                DB::raw('COUNT(*) as total_remarks')
            )
            ->groupBy('added_by', DB::raw('DATE(created_at)'))
            ->orderBy('remark_date', 'desc')
            ->get();

        $lifetimeRemarks = HotelRemark::with('user')
            ->select('added_by', DB::raw('COUNT(*) as total_remarks'))
            ->groupBy('added_by')
            ->get();

        return compact(
            'totalTickets',
            'pendingTickets',
            'feedbackedTickets',
            'bookedTickets',
            'latestTickets',
            'remarksByDate',
            'lifetimeRemarks'
        );
    }
}
