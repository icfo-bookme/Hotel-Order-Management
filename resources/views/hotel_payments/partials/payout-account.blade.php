<div class="min-w-48 whitespace-normal text-sm">
    @if ($account)
        <p class="font-semibold">{{ ['bank' => 'Bank', 'bkash' => 'bKash', 'nagad' => 'Nagad'][$account->payment_method] ?? $account->payment_method }}</p>
        <p>{{ $account->account_name }}</p><p class="break-all">{{ $account->account_number }}</p>
        @if ($account->bank_name)<p>{{ $account->bank_name }}</p>@endif
        @if ($account->branch_name)<p>Branch: {{ $account->branch_name }}</p>@endif
        @if ($account->routing_number)<p>Routing: {{ $account->routing_number }}</p>@endif
    @else
        <p class="text-amber-700">No payout payment method added for this hotel. Please add one.</p>
        <a href="{{ route('hotel-payment-accounts.index') }}" class="mt-1 inline-block font-semibold text-blue-700 underline">Add Payment Method</a>
    @endif
</div>
