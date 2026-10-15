<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelBookingPaymentRequest;
use App\Http\Requests\UpdateHotelBookingPaymentRequest;
use App\Http\Requests\VerifyHotelPaymentsRequest;
use App\Http\Requests\ReviewHotelPaymentRequest;
use App\Models\HotelBookingPayment;
use App\Models\ShipTicketSale;
use App\Services\HotelPaymentService;
use App\Services\HotelBookingService;
use App\Services\PaymentAttachmentService;
use Illuminate\Http\JsonResponse;

class HotelBookingPaymentController extends Controller
{
    public function verifySale(VerifyHotelPaymentsRequest $request, ShipTicketSale $shipTicketSale): JsonResponse
    {
        $count = $this->service->verifySale($shipTicketSale, (int) $request->user()->id);

        return response()->json(['message' => $count ? $count.' payment(s) verified successfully.' : 'No pending payments to verify.', 'verified_count' => $count]);
    }

    public function review(ReviewHotelPaymentRequest $request, HotelBookingPayment $hotelBookingPayment): JsonResponse
    {
        $payment = $this->service->review($hotelBookingPayment, $request->validated(), (int) $request->user()->id);

        return response()->json(['message' => 'Payment '.$payment->status.' successfully.', 'data' => $payment]);
    }

    public function attachment(HotelBookingPayment $hotelBookingPayment, PaymentAttachmentService $attachments)
    {
        return $attachments->view($hotelBookingPayment);
    }

    public function __construct(
        private readonly HotelPaymentService $service,
        private readonly HotelBookingService $bookings,
    ) {
    }

    public function store(StoreHotelBookingPaymentRequest $request): JsonResponse
    {
        $payment = $this->service->create($request->validated(), (int) $request->user()->id);

        return response()->json(['message' => 'Booking payment recorded successfully.', 'data' => $payment, 'totals' => $this->bookings->totals($payment->booking)], 201);
    }

    public function show(HotelBookingPayment $hotelBookingPayment): JsonResponse
    {
        return response()->json(['data' => $hotelBookingPayment]);
    }

    public function update(UpdateHotelBookingPaymentRequest $request, HotelBookingPayment $hotelBookingPayment): JsonResponse
    {
        $payment = $this->service->update($hotelBookingPayment, $request->validated());

        return response()->json(['message' => 'Booking payment updated successfully.', 'data' => $payment, 'totals' => $this->bookings->totals($payment->booking)]);
    }
}
