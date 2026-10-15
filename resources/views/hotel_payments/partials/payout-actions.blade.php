<div class="flex flex-col gap-2">
    @unless ($paid)
        <button type="button" data-payout @disabled($balance['reason'] || $balance['available'] === '0.00') class="rounded-lg bg-blue-950 px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Payout</button>
        <button type="button" data-set-advances class="text-xs font-semibold text-blue-700 underline">Set Hotel Advances</button>
    @else
        <span class="text-sm font-semibold text-green-700">Paid</span>
    @endunless
</div>
