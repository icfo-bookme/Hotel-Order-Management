<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\HotelBookingRoom;
use App\Models\HotelBookingPayment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class HotelReportService
{
    public function hotels()
    {
        return HotelBookingRoom::whereHas('booking')->whereNotNull('bookme_hotel_id')->select('bookme_hotel_id')
            ->selectRaw('MAX(hotel_name) AS hotel_name')->groupBy('bookme_hotel_id')->orderBy('hotel_name')->get();
    }

    public function query(array $filters): Builder
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $days = $sqlite ? 'MAX(0, CAST(julianday(check_out_date)-julianday(check_in_date) AS INTEGER))' : 'GREATEST(0, DATEDIFF(check_out_date, check_in_date))';
        $integer = $sqlite ? 'INTEGER' : 'SIGNED';
        $rooms = DB::table('hotel_booking_rooms')->select('hotel_booking_id')
            ->selectRaw('SUM(CAST(ROUND(customer_price_per_night*100,0) AS '.$integer.')*'.$days.' + COALESCE((SELECT SUM(CAST(ROUND(rs.customer_fee*100,0) AS '.$integer.')) FROM hotel_booking_reschedules rs WHERE rs.hotel_booking_room_id=hotel_booking_rooms.id),0)) AS sell_cents')
            ->selectRaw('SUM(CAST(ROUND(partner_price_per_night*100,0) AS '.$integer.')*'.$days.' + COALESCE((SELECT SUM(CAST(ROUND(rs.partner_fee*100,0) AS '.$integer.')) FROM hotel_booking_reschedules rs WHERE rs.hotel_booking_room_id=hotel_booking_rooms.id),0)) AS partner_cents')->groupBy('hotel_booking_id');
        $payments = DB::table('hotel_booking_payments')->select('hotel_booking_id')
            ->selectRaw("SUM(CASE WHEN status='verified' THEN CAST(ROUND(amount*100,0) AS ".$integer.") ELSE 0 END) AS verified_cents")->groupBy('hotel_booking_id');
        $payouts = DB::table('hotel_payouts')->select('hotel_booking_id')
            ->selectRaw('SUM(CAST(ROUND(amount*100,0) AS '.$integer.')) AS payout_cents')->groupBy('hotel_booking_id');
        $timezone = config('app.timezone');
        $from = CarbonImmutable::parse($filters['date_from'], 'Asia/Dhaka')->startOfDay()->setTimezone($timezone);
        $to = CarbonImmutable::parse($filters['date_to'], 'Asia/Dhaka')->endOfDay()->setTimezone($timezone);

        return HotelBooking::query()->where('hotel_bookings.status', 'confirmed')
            ->whereHas('sale', fn ($sale) => $sale->where('hotel_status', 'sold')->withVerifiedHotelPayments())
            ->whereBetween('hotel_bookings.created_at', [$from, $to])
            ->joinSub($rooms, 'room_totals', 'room_totals.hotel_booking_id', '=', 'hotel_bookings.id')
            ->leftJoinSub($payments, 'payment_totals', 'payment_totals.hotel_booking_id', '=', 'hotel_bookings.id')
            ->leftJoinSub($payouts, 'payout_totals', 'payout_totals.hotel_booking_id', '=', 'hotel_bookings.id')
            ->select('hotel_bookings.*', 'room_totals.sell_cents', 'room_totals.partner_cents')
            ->selectRaw('COALESCE(verified_cents,0) AS verified_cents, COALESCE(payout_cents,0) AS payout_cents')
            ->selectRaw('sell_cents-partner_cents AS profit_cents, sell_cents-COALESCE(verified_cents,0) AS due_cents')
            ->when(!empty($filters['hotel_id']), fn ($query) => $query->whereHas('rooms', fn ($room) => $room->where('bookme_hotel_id', $filters['hotel_id'])))
            ->when(!empty($filters['payment_method']), fn ($query) => $query->whereHas('payments', fn ($payment) => $payment->where('status', 'verified')->where('payment_method', $filters['payment_method'])))
            ->when(isset($filters['q']) && $filters['q'] !== '', fn ($query) => $query->whereHas('sale', fn ($sale) => $sale->where(fn ($customer) => $customer
                ->where('customer_name', 'like', '%'.$filters['q'].'%')->orWhere('customer_mobile', 'like', '%'.$filters['q'].'%')->orWhere('whatsapp', 'like', '%'.$filters['q'].'%'))))
            ->with(['sale', 'rooms.reschedules', 'creator']);
    }

    private function summary(Builder $query): array
    {
        $query = $query->toBase()->cloneWithout(['orders', 'limit', 'offset'])->cloneWithoutBindings(['order']);
        $row = DB::query()->fromSub($query, 'report')
            ->selectRaw('COUNT(*) AS bookings, SUM(sell_cents) AS sell, SUM(partner_cents) AS partner, SUM(profit_cents) AS profit, SUM(verified_cents) AS received, SUM(due_cents) AS due, SUM(payout_cents) AS paid')->first();
        $totals = ['bookings' => (int) $row->bookings];
        foreach (['sell', 'partner', 'profit', 'received', 'due', 'paid'] as $field) {
            $totals[$field] = Money::format((int) $row->$field);
        }

        return $totals;
    }

    public function table(array $filters)
    {
        $table = DataTables::eloquent($this->query($filters))
            ->addColumn('customer', fn ($booking) => view('hotel_payments.partials.customer', ['sale' => $booking->sale])->render())
            ->filterColumn('customer', function ($query, $keyword) {
                $query->whereHas('sale', fn ($sale) => $sale->where(fn ($customer) => $customer
                    ->where('customer_name', 'like', '%'.$keyword.'%')->orWhere('customer_mobile', 'like', '%'.$keyword.'%')->orWhere('whatsapp', 'like', '%'.$keyword.'%')));
            })
            ->addColumn('hotels', fn ($booking) => view('ship_tickets.partials.booking-cells', ['rooms' => $booking->rooms, 'dates' => false, 'includeDates' => true])->render())
            ->addColumn('sold_by', fn ($booking) => $booking->creator?->name ?? '-')
            ->editColumn('created_at', fn ($booking) => $booking->created_at->timezone('Asia/Dhaka')->format('d M Y, h:i A'));
        foreach (['sell', 'partner', 'profit', 'verified', 'due', 'payout'] as $field) {
            $table->addColumn($field, fn ($booking) => Money::format((int) $booking->{$field.'_cents'}));
        }

        return $table->rawColumns(['customer', 'hotels'])->escapeColumns(['sold_by'])
            ->withQuery('summary', fn ($query) => $this->summary($query))
            ->withQuery('payment_methods', fn ($query) => $this->paymentMethods($query))->toJson();
    }

    private function paymentMethods(Builder $query): array
    {
        $filtered = $query->toBase()->cloneWithout(['orders', 'limit', 'offset'])->cloneWithoutBindings(['order']);
        $ids = DB::query()->fromSub($filtered, 'report')->select('report.id');
        $integer = DB::connection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'SIGNED';
        $rows = HotelBookingPayment::whereIn('hotel_booking_id', $ids)->where('status', 'verified')
            ->select('payment_method')->selectRaw('COUNT(*) AS payment_count, SUM(CAST(ROUND(amount*100,0) AS '.$integer.')) AS received_cents')
            ->groupBy('payment_method')->get()->keyBy('payment_method');
        $labels = ['cash' => 'Cash', 'bank' => 'Bank', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'card' => 'Card', 'other' => 'Other'];
        foreach ($rows->keys() as $method) {
            $labels[$method] ??= ucfirst($method);
        }

        return collect($labels)->map(fn ($label, $method) => [
            'method' => $method, 'label' => $label,
            'received' => Money::format((int) ($rows->get($method)?->received_cents ?? 0)),
            'count' => (int) ($rows->get($method)?->payment_count ?? 0),
        ])->values()->all();
    }

    public function export(array $filters): StreamedResponse
    {
        return response()->streamDownload(function () use ($filters) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Booking ID', 'Sale ID', 'Booking Date (Dhaka)', 'Customer', 'Mobile', 'WhatsApp', 'Hotels', 'Customer Total', 'Partner Total', 'B2B Profit', 'Verified Received', 'Due/Hotel Received', 'Paid to Hotels', 'Sold By'], ',', '"', '');
            foreach ($this->query($filters)->lazyById(500, 'hotel_bookings.id', 'id') as $booking) {
                $text = [$booking->sale->customer_name, $booking->sale->customer_mobile, $booking->sale->whatsapp, $booking->rooms->pluck('hotel_name')->unique()->implode('; ')];
                $text = array_map($this->csvText(...), $text);
                $money = [];
                foreach (['sell', 'partner', 'profit', 'verified', 'due', 'payout'] as $field) {
                    $money[] = Money::format((int) $booking->{$field.'_cents'});
                }
                fputcsv($file, [$booking->id, $booking->sales_id, $booking->created_at->timezone('Asia/Dhaka')->format('Y-m-d H:i'), ...$text, ...$money, $this->csvText($booking->creator?->name ?? '-')], ',', '"', '');
            }
            fclose($file);
        }, 'hotel-report-'.$filters['date_from'].'-to-'.$filters['date_to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function csvText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^\s*[=+@\-]/u', $value) ? "'".$value : $value;
    }
}
