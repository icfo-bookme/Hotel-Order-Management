<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Support\Money;
use App\Models\HotelBookingAdvance;
use App\Models\HotelPaymentAccount;
use App\Models\HotelPayout;
use App\Models\ShipTicketSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelPayoutService
{
    public function __construct(private readonly HotelBookingService $bookings, private readonly PaymentAttachmentService $attachments)
    {
    }

    public function balances(HotelBookingAdvance $advance): array
    {
        if (!$advance->bookme_hotel_id || $advance->advance_amount === null) {
            throw ValidationException::withMessages(['hotel_advances' => 'Hotel ID and an explicit advance amount are required.']);
        }
        $booking = $advance->booking;
        $rooms = $booking->rooms->where('bookme_hotel_id', $advance->bookme_hotel_id);
        $sell = $rooms->sum(fn ($room) => Money::cents($room->customer_sell));
        $partner = $rooms->sum(fn ($room) => Money::cents($room->partner_share));
        $profit = $sell - $partner;
        $received = Money::cents($advance->advance_amount);
        $paid = $booking->payouts->where('bookme_hotel_id', $advance->bookme_hotel_id)->sum(fn ($payout) => Money::cents($payout->amount));
        $target = $received - $profit;
        $allocated = $booking->advances->sum(fn ($item) => Money::cents($item->advance_amount));
        $actual = $booking->payments->sum(fn ($payment) => Money::cents($payment->amount));
        $bookingProfit = $booking->rooms->sum(fn ($room) => Money::cents($room->customer_sell) - Money::cents($room->partner_share));
        $bookingPaid = $booking->payouts->sum(fn ($payout) => Money::cents($payout->amount));
        $advance->loadMissing('paymentAccounts');
        $accounts = $advance->paymentAccounts->where('is_active', true)->values();
        $reason = null;
        if ($allocated !== $actual) {
            $reason = 'Set hotel advances to match the received payment amount.';
        } elseif ($target < 0) {
            $reason = 'Hotel advance is less than B2B profit. Review the advance before payout.';
        } elseif ($accounts->isEmpty()) {
            $reason = 'This hotel needs an active payout payment account.';
        }

        return [
            'customer_total' => Money::format($sell),
            'advance' => $advance->advance_amount,
            'profit' => Money::format($profit),
            'customer_due' => Money::format($sell - $received),
            'payout_amount' => Money::format($target),
            'paid' => Money::format($paid),
            'remaining' => Money::format(max(0, $target - $paid)),
            'available' => Money::format(max(0, min($target - $paid, $actual - $bookingProfit - $bookingPaid))),
            'reason' => $reason, 'accounts' => $accounts,
        ];
    }

    public function create(HotelBookingAdvance $advance, array $data, int $userId): HotelPayout
    {
        return $this->attachments->transaction(function ($upload) use ($advance, $data, $userId) {
            $booking = HotelBooking::findOrFail($advance->hotel_booking_id);
            $sale = ShipTicketSale::lockForUpdate()->findOrFail($booking->sales_id);
            $booking = HotelBooking::lockForUpdate()->findOrFail($booking->id);
            $existing = HotelPayout::where('submission_id', $data['submission_id'])->first();
            if ($existing) {
                if ($existing->hotel_booking_id !== $booking->id || (string) $existing->bookme_hotel_id !== (string) $advance->bookme_hotel_id || Money::cents($existing->amount) !== Money::cents((string) $data['amount']) || $existing->hotel_payment_account_id !== (int) $data['hotel_payment_account_id'] || (int) $existing->created_by !== $userId) {
                    throw ValidationException::withMessages(['submission_id' => 'This submission was already used for another payout.']);
                }

                return $existing;
            }
            $paymentsVerified = ShipTicketSale::whereKey($sale->id)->withVerifiedHotelPayments()->exists();
            if ($sale->hotel_status !== 'sold' || $booking->status !== 'confirmed' || !$paymentsVerified) {
                throw ValidationException::withMessages(['payment' => 'All customer payments for this booking must be verified before payout.']);
            }
            $advance = HotelBookingAdvance::lockForUpdate()->findOrFail($advance->id);
            $advance->setRelation('booking', $booking->load(['rooms.reschedules', 'payments', 'advances', 'payouts']));
            $balance = $this->balances($advance);
            if ($balance['reason']) {
                throw ValidationException::withMessages(['payout' => $balance['reason']]);
            }
            $amount = Money::cents((string) $data['amount']);
            if ($amount <= 0 || $amount > Money::cents($balance['available'])) {
                throw ValidationException::withMessages(['amount' => 'Amount must be positive and cannot exceed the hotel remaining or available received funds.']);
            }
            $account = HotelPaymentAccount::whereKey($data['hotel_payment_account_id'])->where('bookme_hotel_id', $advance->bookme_hotel_id)->where('is_active', true)->lockForUpdate()->first();
            if (!$account) {
                throw ValidationException::withMessages(['hotel_payment_account_id' => 'Select an active payment account belonging to this hotel.']);
            }
            $snapshot = $account->only(['payment_method', 'account_name', 'account_number', 'bank_name', 'branch_name', 'routing_number']);
            $data = $upload($data);

            return HotelPayout::create(array_merge($snapshot, [
                'submission_id' => $data['submission_id'], 'hotel_booking_id' => $booking->id,
                'bookme_hotel_id' => $advance->bookme_hotel_id, 'hotel_payment_account_id' => $account->id,
                'amount' => Money::format($amount), 'paid_at' => now(), 'created_by' => $userId,
                'transaction_reference' => $data['transaction_reference'] ?? null, 'remark' => $data['remark'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
            ]));
        });
    }

    public function updateAdvances(HotelBooking $booking, array $allocations): void
    {
        DB::transaction(function () use ($booking, $allocations) {
            ShipTicketSale::lockForUpdate()->findOrFail($booking->sales_id);
            $booking = HotelBooking::lockForUpdate()->findOrFail($booking->id);
            $this->bookings->allocateAdvances($booking, $allocations);
            $booking->load(['rooms.reschedules', 'payments', 'advances', 'payouts']);
            foreach ($booking->advances as $advance) {
                $advance->setRelation('booking', $booking);
                $balance = $this->balances($advance);
                if (Money::cents($balance['paid']) > max(0, Money::cents($balance['payout_amount']))) {
                    throw ValidationException::withMessages(['hotel_advances' => 'Advance cannot reduce the hotel payout below the amount already paid.']);
                }
            }
        });
    }
}
