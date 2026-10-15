<?php

namespace App\Http\Controllers;

use App\Services\BookmeService;
use Illuminate\Http\JsonResponse;
use Throwable;

class HotelOptionController extends Controller
{
    public function rooms(int $hotelId, BookmeService $service): JsonResponse
    {
        try {
            return response()->json(['results' => $service->rooms($hotelId)]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Room list could not be loaded. Please try again.'], 502);
        }
    }

    public function index(BookmeService $service): JsonResponse
    {
        try {
            return response()->json(['results' => $service->all()]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Hotel list could not be loaded. Please try again.'], 502);
        }
    }
}
