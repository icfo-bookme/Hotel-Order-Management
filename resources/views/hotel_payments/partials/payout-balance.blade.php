<div class="min-w-56 space-y-1 whitespace-normal text-sm">
    <p>Advance Received: <strong>{{ $balance['advance'] }}</strong></p>
    <p>B2B Profit: <strong>{{ $balance['profit'] }}</strong></p>
    <p>Customer pays hotel: <strong>{{ $balance['customer_due'] }}</strong></p>
    <p>Paid to hotel: <strong>{{ $balance['paid'] }}</strong></p>
    <p>Remaining payout: <strong>{{ $balance['remaining'] }}</strong></p>
    <p>Available to pay now: <strong>{{ $balance['available'] }}</strong></p>
    @if ($balance['reason'])<p class="mt-2 text-xs text-amber-700">{{ $balance['reason'] }}</p>@endif
    @foreach ($payouts as $payout)
        <div class="mt-2 rounded border border-gray-200 p-2 text-xs">
            <p>Payout #{{ $payout->id }}: {{ $payout->amount }} · {{ $payout->paid_at?->format('d M Y, h:i A') }}</p>
            <p>{{ $payout->payment_method }} · {{ $payout->account_name }} · {{ $payout->account_number }}</p>
            <p>Recorded by: {{ $payout->creator?->name ?? '-' }}</p>
            @if ($payout->transaction_reference)<p>Reference: {{ $payout->transaction_reference }}</p>@endif
        </div>
    @endforeach
</div>
