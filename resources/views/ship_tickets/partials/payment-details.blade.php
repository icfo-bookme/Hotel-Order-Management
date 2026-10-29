<div class="min-w-64 max-w-sm space-y-4 whitespace-normal text-sm">
    @forelse ($bookings as $booking)
        <div class="space-y-3">
            @if ($bookings->count() > 1)
                <p class="font-semibold text-gray-800">Booking #{{ $booking['id'] }}</p>
            @endif
            <div class="grid grid-cols-3 gap-3 rounded-lg bg-gray-50 p-3">
                <div><span class="block text-xs text-gray-500">Total Sell</span><strong
                        class="text-gray-800">{{ $booking['totals']['customer_sell'] }}</strong></div>
                <div><span class="block text-xs text-gray-500">Received</span><strong
                        class="text-green-700">{{ $booking['totals']['received'] }}</strong></div>
                <div><span class="block text-xs text-gray-500">{{ $dueLabel ?? 'Due' }}</span><strong
                        class="text-gray-800">{{ $booking['totals']['due'] }}</strong></div>
            </div>
            @foreach ($booking['removed_rooms'] ?? [] as $removedRoom)
                <p class="rounded-lg bg-red-50 p-2 text-xs text-red-800">Removed room: {{ $removedRoom->hotel_name }} /
                    {{ $removedRoom->room_name }} · {{ $removedRoom->removal_reason }}</p>
            @endforeach
            @forelse ($booking['payments'] as $payment)
                <div class="space-y-1 rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-4">
                        <span class="font-semibold text-gray-800">{{ $payment->refunded_room_id ? 'Room Refund' : 'Payment' }} #{{ $loop->iteration }}</span>
                        <strong class="text-green-700">{{ $payment->amount }}</strong>
                    </div>
                    <p class="text-gray-600"><span class="text-gray-500">Method:</span>
                        {{ ['cash' => 'Cash', 'bank' => 'Bank', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'card' => 'Card', 'other' => 'Other'][$payment->payment_method] ?? $payment->payment_method }}
                    </p>
                    <p class="text-gray-600"><span class="text-gray-500">Other Fee:</span> {{ $payment->other_fee }}</p>
                    <span @class([
                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                        'bg-amber-100 text-amber-800' => $payment->status === 'pending',
                        'bg-green-100 text-green-800' => $payment->status === 'verified',
                        'bg-red-100 text-red-800' => $payment->status === 'rejected',
                    ])>{{ ucfirst($payment->status) }}</span>
                    @if ($payment->status === 'verified')
                        <p class="text-xs text-gray-500">Verified by {{ $payment->verifier?->name ?? '-' }} ·
                            {{ $payment->verified_at?->format('d M Y, h:i A') }}</p>
                    @endif
                    @if ($payment->verification_note)
                        <p class="whitespace-pre-line break-words text-gray-600">{{ $payment->verification_note }}</p>
                    @endif
                    <p class="text-gray-600"><span class="text-gray-500">Paid at:</span>
                        {{ $payment->paid_at?->format('d M Y, h:i A') ?? '-' }}</p>
                    <p class="break-words text-gray-600"><span class="text-gray-500">Reference:</span>
                        {{ $payment->transaction_reference ?: '-' }}</p>
                    <p class="text-gray-600"><span class="text-gray-500">Recorded by:</span>
                        {{ $payment->creator?->name ?? '-' }}</p>
                    @if ($payment->remark)
                        <p class="whitespace-pre-line break-words text-gray-600"><span
                                class="text-gray-500">Remark:</span> {{ $payment->remark }}</p>
                    @endif
                    @if ($payment->attachment_path && auth()->user()->can('payments.screenshot'))
                        <button type="button"
                            data-view-payment-screenshot="{{ route('hotel-booking-payments.attachment', $payment) }}"
                            title="View Screenshot" aria-label="View Screenshot"
                            class="mt-2 block rounded-lg border border-gray-200 p-1 transition-colors hover:border-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600">
                            <img src="{{ route('hotel-booking-payments.attachment', $payment) }}" loading="lazy"
                                alt="Payment #{{ $loop->iteration }} screenshot"
                                class="h-20 w-28 rounded object-contain" />
                        </button>
                    @endif
                </div>
            @empty
                <p class="text-gray-500">No payments recorded.</p>
            @endforelse
        </div>
    @empty
        <span class="text-gray-400">-</span>
    @endforelse
</div>
