<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListHotelBookingsRequest;
use App\Http\Requests\StoreHotelBookingRequest;
use App\Http\Requests\UpdateHotelBookingRequest;
use App\Models\HotelBooking;
use App\Services\HotelBookingService;
use Illuminate\Http\JsonResponse;

class HotelBookingController extends Controller
{
    public function __construct(private readonly HotelBookingService $service)
    {
    }

    public function index(ListHotelBookingsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $bookings = $this->service->paginate($filters);

        return response()->json($bookings);
    }

    public function store(StoreHotelBookingRequest $request): JsonResponse
    {
        $booking = $this->service->create($request->validated(), (int) $request->user()->id);

        return response()->json(['message' => 'Hotel booking created successfully.', 'data' => $this->service->details($booking)], 201);
    }

    public function show(HotelBooking $hotelBooking): JsonResponse
    {
        return response()->json(['data' => $this->service->details($hotelBooking)]);
    }

    public function update(UpdateHotelBookingRequest $request, HotelBooking $hotelBooking): JsonResponse
    {
        $booking = $this->service->update($hotelBooking, $request->validated());

        return response()->json(['message' => 'Hotel booking updated successfully.', 'data' => $this->service->details($booking)]);
    }
}
