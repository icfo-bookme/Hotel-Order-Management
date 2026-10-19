@if ($payout->attachment_path)
    <button type="button" data-view-payout-screenshot="{{ route('hotel-payouts.attachment', $payout) }}" title="View Payment Screenshot" aria-label="View Payment Screenshot" class="rounded-lg border border-gray-200 p-1 hover:border-blue-500">
        <img src="{{ route('hotel-payouts.attachment', $payout) }}" loading="lazy" alt="Payout screenshot" class="h-16 w-24 rounded object-contain" />
    </button>
@else
    <span class="text-gray-400">-</span>
@endif
