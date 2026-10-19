<?php

namespace App\Http\Controllers;

use App\Services\HotelPayoutQueryService;
use App\Services\HotelPayoutService;
use App\Http\Requests\StoreHotelPayoutRequest;
use App\Http\Requests\UpdateHotelAdvancesRequest;
use App\Models\HotelBookingAdvance;
use App\Models\HotelBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HotelPayoutController extends Controller
{
    public function attachment(\App\Models\HotelPayout $hotelPayout, \App\Services\PaymentAttachmentService $service): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $service->view($hotelPayout);
    }

    public function pending(): View
    {
        return view('hotel_payments.pending-payouts');
    }

    public function paid(): View
    {
        return view('hotel_payments.paid-payouts');
    }

    public function paidData(HotelPayoutQueryService $service)
    {
        return $service->paid();
    }

    public function store(StoreHotelPayoutRequest $request, HotelBookingAdvance $hotelBookingAdvance, HotelPayoutService $service): JsonResponse
    {
        $payout = $service->create($hotelBookingAdvance, $request->validated(), (int) $request->user()->id);

        return response()->json(['message' => 'Hotel payout recorded successfully.', 'data' => $payout], 201);
    }

    public function updateAdvances(UpdateHotelAdvancesRequest $request, HotelBooking $hotelBooking, HotelPayoutService $service): JsonResponse
    {
        $service->updateAdvances($hotelBooking, $request->validated()['hotel_advances']);

        return response()->json(['message' => 'Hotel advances updated.']);
    }

    public function data(HotelPayoutQueryService $service)
    {
        return $service->pending();
    }
}
