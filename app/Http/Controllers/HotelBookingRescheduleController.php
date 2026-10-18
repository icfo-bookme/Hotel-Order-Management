<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelBookingRescheduleRequest;
use App\Models\HotelBookingRoom;
use App\Models\ShipTicketSale;
use App\Services\HotelBookingService;
use Illuminate\Http\JsonResponse;

class HotelBookingRescheduleController extends Controller
{
    public function __construct(private readonly HotelBookingService $service)
    {
    }

    public function index(ShipTicketSale $shipTicketSale): JsonResponse
    {
        return response()->json(['rooms' => $this->service->rescheduleRooms($shipTicketSale)]);
    }

    public function store(StoreHotelBookingRescheduleRequest $request, HotelBookingRoom $hotelBookingRoom): JsonResponse
    {
        $this->service->reschedule($hotelBookingRoom, $request->validated(), (int) $request->user()->id);

        return response()->json(['message' => 'Room rescheduled successfully. Any additional payment is pending verification.']);
    }
}
