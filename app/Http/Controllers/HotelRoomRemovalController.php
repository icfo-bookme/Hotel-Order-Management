<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemoveHotelBookingRoomRequest;
use App\Models\HotelBookingRoom;
use App\Models\ShipTicketSale;
use App\Services\GoogleSheetService;
use App\Services\HotelBookingService;
use App\Services\HotelRoomRemovalService;

class HotelRoomRemovalController extends Controller
{
    public function index(ShipTicketSale $shipTicketSale, HotelRoomRemovalService $service)
    {
        return response()->json(['rooms' => $service->rooms($shipTicketSale)]);
    }

    public function store(RemoveHotelBookingRoomRequest $request, HotelBookingRoom $hotelBookingRoom, HotelRoomRemovalService $service, HotelBookingService $bookings, GoogleSheetService $sheets)
    {
        $booking = $service->remove($hotelBookingRoom, $request->validated(), (int) $request->user()->id);
        $synced = $sheets->syncBooking($booking, true);

        return response()->json([
            'message' => 'Room removed and booking balances adjusted.',
            'totals' => $bookings->totals($booking),
            'sheet_warning' => $synced === false ? 'Room removed, but Google Sheet sync failed. Please contact an administrator.' : null,
        ]);
    }
}
