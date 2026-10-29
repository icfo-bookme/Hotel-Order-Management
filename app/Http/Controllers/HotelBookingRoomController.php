<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelBookingRoomRequest;
use App\Http\Requests\UpdateHotelBookingRoomRequest;
use App\Models\HotelBookingRoom;
use App\Models\ShipTicketSale;
use App\Services\GoogleSheetService;
use App\Services\HotelBookingService;
use Illuminate\Http\JsonResponse;

class HotelBookingRoomController extends Controller
{
    public function __construct(
        private readonly HotelBookingService $bookings,
    ) {
    }

    public function store(StoreHotelBookingRoomRequest $request): JsonResponse
    {
        $room = $this->bookings->createRoom($request->validated());

        return response()->json(['message' => 'Booking room created successfully.', 'data' => $room, 'totals' => $this->bookings->totals($room->booking)], 201);
    }

    public function show(HotelBookingRoom $hotelBookingRoom): JsonResponse
    {
        abort_unless($hotelBookingRoom->booking()->exists(), 404);
        return response()->json(['data' => $hotelBookingRoom]);
    }

    public function index(ShipTicketSale $shipTicketSale): JsonResponse
    {
        return response()->json(['rooms' => $this->bookings->rescheduleRooms($shipTicketSale)]);
    }

    public function update(UpdateHotelBookingRoomRequest $request, HotelBookingRoom $hotelBookingRoom, GoogleSheetService $sheets): JsonResponse
    {
        $room = $this->bookings->updateRoom($hotelBookingRoom, $request->validated());

        $booking = $room->booking;
        $synced = $sheets->syncBooking($booking, true);

        return response()->json([
            'message' => 'Booking room updated successfully.', 'data' => $room,
            'totals' => $this->bookings->totals($booking),
            'sheet_warning' => $synced === false ? 'Room updated, but Google Sheet sync failed. Please contact an administrator.' : null,
        ]);
    }
}
