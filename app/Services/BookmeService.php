<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class BookmeService
{
    public function all(): array
    {
        return Cache::remember('bookme.hotel-options.v3', now()->addMinutes(5), function (): array {
            $hotels = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get(config('services.bookme.hotels_url'))
                ->throw()
                ->json();

            if (!is_array($hotels) || !array_is_list($hotels)) {
                throw new UnexpectedValueException('Invalid hotel listing response.');
            }

            return collect($hotels)
                ->filter(fn ($hotel) => is_array($hotel) && filter_var($hotel['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false && is_string($hotel['name'] ?? null) && trim($hotel['name']) !== '')
                ->map(fn (array $hotel) => ['id' => (string) $hotel['id'], 'text' => trim($hotel['name']), 'hotel_id' => (int) $hotel['id']])
                ->unique('id')
                ->sortBy('text', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        });
    }

    public function rooms(int $hotelId): array
    {
        return Cache::remember('bookme.room-options.v2.'.$hotelId, now()->addMinutes(5), function () use ($hotelId): array {
            $rooms = Http::acceptJson()->connectTimeout(5)->timeout(15)
                ->get(rtrim(config('services.bookme.rooms_url'), '/').'/'.$hotelId)
                ->throw()->json();

            if (!is_array($rooms) || !array_is_list($rooms)) {
                throw new UnexpectedValueException('Invalid room listing response.');
            }

            return collect($rooms)
                ->filter(fn ($room) => is_array($room) && is_string($room['room_name'] ?? null) && trim($room['room_name']) !== '')
                ->map(fn (array $room) => [
                    'id' => trim($room['room_name']),
                    'text' => trim($room['room_name']),
                    'customer_price_per_night' => $room['price_segments'][0]['final_segment_price'] ?? null,
                ])
                ->unique('id')->sortBy('text', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        });
    }
}
