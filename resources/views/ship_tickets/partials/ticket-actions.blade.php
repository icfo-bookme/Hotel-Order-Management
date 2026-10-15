<div class="flex items-center justify-center gap-2">
    @if (in_array($status, ['pending', 'feedbacked'], true))
        @include('ship_tickets.partials.book-hotel-button')
    @endif
    @if ($status === 'pending')
        <button type="button" title="Add Feedback" aria-label="Add Feedback" class="btn-remarks inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition-colors hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 0 1-9 9H3v-9a9 9 0 0 1 18 0ZM8 12h8m-8 4h5" /></svg>
        </button>
    @elseif ($status === 'feedbacked')
        <button type="button" title="Edit Feedback" aria-label="Edit Feedback" class="btn-edit inline-flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700 transition-colors hover:bg-green-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m16 4 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15z" /></svg>
        </button>
    @elseif ($status === 'sold')
        <button type="button" @disabled(!($canVerify ?? false)) title="{{ ($canVerify ?? false) ? 'Verify Pending Payments' : 'No Pending Payments' }}" aria-label="Verify Pending Payments" class="btn-verify-payments inline-flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700 hover:bg-green-100 disabled:cursor-default disabled:opacity-40">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" /></svg>
        </button>
    @elseif ($status === 'booked')
        <span role="img" title="{{ $status === 'sold' ? 'Sold' : 'Already Booked' }}" aria-label="{{ $status === 'sold' ? 'Sold' : 'Already Booked' }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" /></svg>
        </span>
    @endif
</div>
