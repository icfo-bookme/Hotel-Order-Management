<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListShipTicketsRequest;
use App\Http\Requests\SellHotelBookingRequest;
use App\Http\Requests\StoreHotelRemarkRequest;
use App\Http\Requests\UpdateHotelRemarkRequest;
use App\Models\ShipTicketSale;
use App\Services\HotelBookingService;
use App\Services\HotelRemarkService;
use App\Services\ShipTicketQueryService;

class ShipTicketSaleController extends Controller
{
    public function sell(SellHotelBookingRequest $request, ShipTicketSale $shipTicketSale, HotelBookingService $service)
    {
        $booking = $service->sell($shipTicketSale, $request->validated(), (int) $request->user()->id);

        return response()->json([
            'message' => 'Hotel booking sold successfully.',
            'data' => $service->details($booking),
            'sold_url' => route('ship-tickets.index', 'pending-sold'),
            'invoice_url' => route('hotel-bookings.invoice', $booking),
        ], 201);
    }

    public function index(ListShipTicketsRequest $request)
    {
        return view('ship_tickets.index', ['status' => $request->validated()['status']]);
    }

    public function getData(ListShipTicketsRequest $request, ShipTicketQueryService $service)
    {
        return $service->table($request->validated()['status']);
    }

    public function store(StoreHotelRemarkRequest $request, HotelRemarkService $service)
    {
        $service->store($request->validated(), (int) $request->user()->id);

        return response()->json(['message' => 'Feedback saved successfully.']);
    }

    public function update(UpdateHotelRemarkRequest $request, HotelRemarkService $service)
    {
        $service->update($request->validated());

        return response()->json(['message' => 'Feedback updated successfully.']);
    }

    public function dashboard(ShipTicketQueryService $service)
    {
        return view('dashboard', $service->dashboard());
    }
}
