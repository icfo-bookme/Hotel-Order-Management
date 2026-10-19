<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelPaymentAccountRequest;
use App\Http\Requests\UpdateHotelPaymentAccountRequest;
use App\Models\HotelPaymentAccount;
use App\Services\HotelPaymentAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HotelPaymentAccountController extends Controller
{
    public function index(): View
    {
        return view('hotel_payments.accounts');
    }

    public function data(HotelPaymentAccountService $service)
    {
        return $service->table();
    }

    public function store(StoreHotelPaymentAccountRequest $request, HotelPaymentAccountService $service): JsonResponse
    {
        $account = $service->create($request->validated(), (int) $request->user()->id);

        return response()->json(['message' => 'Hotel payment account added.', 'data' => $account], 201);
    }

    public function update(UpdateHotelPaymentAccountRequest $request, HotelPaymentAccount $hotelPaymentAccount, HotelPaymentAccountService $service): JsonResponse
    {
        $account = $service->update($hotelPaymentAccount, $request->validated());

        return response()->json(['message' => 'Hotel payment account updated.', 'data' => $account]);
    }
}
