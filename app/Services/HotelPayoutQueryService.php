<?php

namespace App\Services;

use App\Models\HotelBookingAdvance;
use App\Models\HotelPayout;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class HotelPayoutQueryService
{
    public function __construct(private readonly HotelPayoutService $payouts)
    {
    }

    public function pending()
    {
        $settled = $this->settledCondition();
        $rows = HotelBookingAdvance::query()->select('hotel_booking_advances.*')
            ->whereNotNull('bookme_hotel_id')->whereNotNull('advance_amount')
            ->whereHas('booking', fn ($booking) => $booking->where('status', 'confirmed')
                ->whereHas('sale', fn ($sale) => $sale->where('hotel_status', 'sold')->withVerifiedHotelPayments()))
            ->with(['booking.sale', 'booking.rooms.reschedules', 'booking.payments', 'booking.advances', 'booking.payouts.creator', 'booking.accounts.paymentAccount', 'booking.creator'])
            ->whereRaw('NOT '.$settled);

        return DataTables::eloquent($rows)
            ->addColumn('customer_name', fn ($row) => view('hotel_payments.partials.customer', ['sale' => $row->booking->sale])->render())
            ->filterColumn('customer_name', function ($query, $keyword) {
                $query->whereHas('booking.sale', fn ($sale) => $sale->where(fn ($customer) => $customer
                    ->where('customer_name', 'like', '%'.$keyword.'%')->orWhere('customer_mobile', 'like', '%'.$keyword.'%')->orWhere('whatsapp', 'like', '%'.$keyword.'%')));
            })
            ->addColumn('hotels', fn ($row) => view('ship_tickets.partials.booking-cells', ['rooms' => $this->rooms($row), 'dates' => false, 'includeDates' => true])->render())
            ->addColumn('balance', fn ($row) => $this->publicBalance($this->payouts->balances($row)))
            ->addColumn('payment_details', fn ($row) => view('hotel_payments.partials.payout-balance', ['balance' => $this->payouts->balances($row), 'payouts' => $row->booking->payouts->where('bookme_hotel_id', $row->bookme_hotel_id)])->render())
            ->addColumn('payout_amount', fn ($row) => $this->payouts->balances($row)['payout_amount'])
            ->addColumn('sold_by', fn ($row) => $row->booking->creator?->name ?? '-')
            ->addColumn('hotel_payment_accounts', fn ($row) => view('hotel_payments.partials.payout-account', ['account' => $this->payouts->balances($row)['account']])->render())
            ->addColumn('allocations', fn ($row) => $row->booking->advances->map(fn ($item) => ['bookme_hotel_id' => $item->bookme_hotel_id, 'hotel_name' => $item->hotel_name, 'amount' => $item->advance_amount])->values()->all())
            ->addColumn('action', fn ($row) => view('hotel_payments.partials.payout-actions', ['balance' => $this->payouts->balances($row)])->render())
            ->rawColumns(['customer_name', 'hotels', 'payment_details', 'hotel_payment_accounts', 'action'])
            ->escapeColumns(['sold_by'])->toJson();
    }

    public function paid()
    {
        $rows = HotelPayout::query()->with(['booking.sale', 'booking.rooms.reschedules', 'creator'])->select('hotel_payouts.*');

        return DataTables::eloquent($rows)
            ->addColumn('customer_name', fn ($row) => view('hotel_payments.partials.customer', ['sale' => $row->booking->sale])->render())
            ->filterColumn('customer_name', function ($query, $keyword) {
                $query->whereHas('booking.sale', fn ($sale) => $sale->where(fn ($customer) => $customer
                    ->where('customer_name', 'like', '%'.$keyword.'%')->orWhere('customer_mobile', 'like', '%'.$keyword.'%')->orWhere('whatsapp', 'like', '%'.$keyword.'%')));
            })
            ->addColumn('hotels', fn ($row) => view('ship_tickets.partials.booking-cells', ['rooms' => $row->booking->rooms->where('bookme_hotel_id', $row->bookme_hotel_id), 'dates' => false, 'includeDates' => true])->render())
            ->addColumn('payment_account', fn ($row) => view('hotel_payments.partials.payout-account', ['account' => $row])->render())
            ->editColumn('paid_at', fn ($row) => $row->paid_at->format('d M Y, h:i A'))
            ->addColumn('recorded_by', fn ($row) => $row->creator?->name ?? '-')
            ->rawColumns(['customer_name', 'hotels', 'payment_account'])
            ->escapeColumns(['recorded_by', 'transaction_reference', 'remark'])->toJson();
    }

    private function settledCondition(): string
    {
        $days = DB::connection()->getDriverName() === 'sqlite'
            ? 'MAX(0, CAST(julianday(r.check_out_date) - julianday(r.check_in_date) AS INTEGER))'
            : 'GREATEST(0, DATEDIFF(r.check_out_date, r.check_in_date))';
        $profit = '(SELECT COALESCE(ROUND(SUM((r.customer_price_per_night-r.partner_price_per_night)*'.$days.' + COALESCE((SELECT SUM(rs.customer_fee-rs.partner_fee) FROM hotel_booking_reschedules rs WHERE rs.hotel_booking_room_id=r.id),0)),2),0)'
            .' FROM hotel_booking_rooms r'
            .' WHERE r.hotel_booking_id=hotel_booking_advances.hotel_booking_id'
            .' AND r.bookme_hotel_id=hotel_booking_advances.bookme_hotel_id)';
        $payouts = '(SELECT COALESCE(SUM(p.amount),0) FROM hotel_payouts p'
            .' WHERE p.hotel_booking_id=hotel_booking_advances.hotel_booking_id'
            .' AND p.bookme_hotel_id=hotel_booking_advances.bookme_hotel_id)';
        $allocationMatches = '(SELECT COALESCE(SUM(a.advance_amount),0) FROM hotel_booking_advances a'
            .' WHERE a.hotel_booking_id=hotel_booking_advances.hotel_booking_id)'
            .' = (SELECT COALESCE(SUM(c.amount),0) FROM hotel_booking_payments c'
            .' WHERE c.hotel_booking_id=hotel_booking_advances.hotel_booking_id)';

        return '(hotel_booking_advances.advance_amount IS NOT NULL AND '.$allocationMatches
            .' AND hotel_booking_advances.advance_amount-'.$profit.' >= 0'
            .' AND '.$payouts.' >= hotel_booking_advances.advance_amount-'.$profit.')';
    }

    private function rooms(HotelBookingAdvance $row)
    {
        return $row->booking->rooms->where('bookme_hotel_id', $row->bookme_hotel_id);
    }

    private function publicBalance(array $balance): array
    {
        $account = $balance['account'];
        $balance['account'] = $account ? $account->only(['payment_method', 'account_name', 'account_number', 'bank_name']) : null;

        return $balance;
    }

}
