<div class="min-w-48 space-y-3 whitespace-normal text-sm">
    @isset($paidAccount)
        <div class="rounded-lg bg-green-50 p-3">
            <p class="mb-1 font-semibold text-green-800">Paid via</p>
            @include('hotel_payments.partials.payout-account', ['account' => $paidAccount])
        </div>
    @else
    @forelse ($accounts as $account)
        <div class="rounded-lg border border-gray-200 p-3">
            @include('hotel_payments.partials.payout-account', ['account' => $account])
            @unless ($account->is_active)<p class="mt-1 text-xs text-gray-500">Inactive</p>@endunless
        </div>
    @empty
        @include('hotel_payments.partials.payout-account', ['account' => null])
    @endforelse
    @endisset
</div>
