<div class="min-w-40 space-y-3 whitespace-normal">
    @forelse ($rooms as $room)
        <div class="min-h-12">
            @if ($dates)
                <p class="text-sm text-gray-700"><span class="text-gray-500">Check-in:</span>
                    {{ $room->check_in_date?->format('d M Y') ?? '-' }}</p>
                <p class="mt-1 text-sm text-gray-700"><span class="text-gray-500">Check-out:</span>
                    {{ $room->check_out_date?->format('d M Y') ?? '-' }}</p>
                @if ($room->reschedules->isNotEmpty())
                    <button type="button"
                        data-reschedule-charges="{{ json_encode(
                            $room->reschedules->sortBy('id')->values()->map(
                                fn($item) => [
                                    'customer_fee' => $item->customer_fee,
                                    'partner_fee' => $item->partner_fee,
                                    'previous_dates' =>
                                        $item->old_check_in_date->format('d M Y') . ' / ' . $item->old_check_out_date->format('d M Y'),
                                    'current_dates' =>
                                        $item->new_check_in_date->format('d M Y') . ' / ' . $item->new_check_out_date->format('d M Y'),
                                ],
                            ),
                        ) }}"
                        class="mt-2 rounded-lg bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Reschedule</button>
                @endif
            @else
                <p class="text-sm font-semibold text-gray-800">{{ $room->hotel_name }}</p>
                <p class="mt-1 text-sm text-gray-500">Room: {{ $room->room_name }}</p>
                @if ($includeDates ?? false)
                    <p class="mt-2 text-sm text-gray-700"><span class="text-gray-500">Check-in:</span>
                        {{ $room->check_in_date?->format('d M Y') ?? '-' }}</p>
                    <p class="mt-1 text-sm text-gray-700"><span class="text-gray-500">Check-out:</span>
                        {{ $room->check_out_date?->format('d M Y') ?? '-' }}</p>
                @endif
            @endif
        </div>
    @empty
        <span class="text-gray-400">-</span>
    @endforelse
</div>
