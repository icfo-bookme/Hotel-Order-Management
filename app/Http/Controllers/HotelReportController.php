<?php

namespace App\Http\Controllers;

use App\Http\Requests\HotelReportRequest;
use App\Services\HotelReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HotelReportController extends Controller
{
    public function index(HotelReportRequest $request, HotelReportService $service): View
    {
        return view('reports.hotel-sales', ['filters' => $request->validated(), 'hotels' => $service->hotels()]);
    }

    public function data(HotelReportRequest $request, HotelReportService $service)
    {
        return $service->table($request->validated());
    }

    public function export(HotelReportRequest $request, HotelReportService $service): StreamedResponse
    {
        return $service->export($request->validated());
    }
}
