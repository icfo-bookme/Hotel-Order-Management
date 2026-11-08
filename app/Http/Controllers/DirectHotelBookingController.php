<?php

namespace App\Http\Controllers;

use App\Http\Requests\SellHotelBookingRequest;
use App\Http\Requests\VerifyHotelPaymentsRequest;
use App\Models\HotelBooking;
use App\Services\GoogleSheetService;
use App\Services\HotelBookingService;
use App\Services\HotelPaymentService;
use App\Services\HotelRoomRemovalService;

class DirectHotelBookingController extends Controller
{
    public function store(SellHotelBookingRequest $request, HotelBookingService $service, GoogleSheetService $sheets)
    {
        $booking = $service->sell(null, $request->validated(), (int) $request->user()->id);
        $synced = $sheets->syncBooking($booking);

        return response()->json([
            'message' => 'Direct hotel booking created successfully.',
            'data' => $service->details($booking),
            'sheet_warning' => $synced === false ? 'Booking saved, but Google Sheet sync failed. Please contact an administrator.' : null,
            'sold_url' => route('ship-tickets.index', 'pending-sold'),
            'invoice_url' => route('hotel-bookings.invoice', $booking),
        ], 201);
    }

    public function rooms(HotelBooking $hotelBooking, HotelBookingService $service)
    {
        abort_unless($hotelBooking->sales_id === null && $hotelBooking->status === 'confirmed', 422);
        return response()->json(['rooms' => $service->roomDetails(collect([$hotelBooking->load(['rooms.reschedules.creator', 'payouts'])]))]);
    }

    public function removableRooms(HotelBooking $hotelBooking, HotelRoomRemovalService $service)
    {
        abort_unless($hotelBooking->sales_id === null && $hotelBooking->status === 'confirmed', 422);
        return response()->json(['rooms' => $service->bookingRooms(new \Illuminate\Database\Eloquent\Collection([$hotelBooking]))]);
    }

    public function verify(VerifyHotelPaymentsRequest $request, HotelBooking $hotelBooking, HotelPaymentService $service)
    {
        abort_unless($hotelBooking->sales_id === null, 422);
        $count = $service->verifyBooking($hotelBooking, (int) $request->user()->id);
        return response()->json(['message' => $count.' payment(s) verified successfully.', 'verified_count' => $count]);
    }
}
