<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelSettingRequest;
use App\Http\Requests\UpdateHotelSettingRequest;
use App\Services\HotelSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HotelSettingController extends Controller
{
    public function index(HotelSettingService $service): View
    {
        abort_unless(auth()->user()?->role?->isSuperAdmin(), 403);

        return view('hotel_settings.index', ['settings' => $service->get()]);
    }

    public function store(StoreHotelSettingRequest $request, HotelSettingService $service): RedirectResponse
    {
        $service->save($request->validated(), (int) $request->user()->id);

        return redirect()->route('hotel-settings.index')->with('status', 'Hotel settings saved successfully.');
    }

    public function update(UpdateHotelSettingRequest $request, HotelSettingService $service): RedirectResponse
    {
        $service->save($request->validated(), (int) $request->user()->id);

        return redirect()->route('hotel-settings.index')->with('status', 'Hotel settings updated successfully.');
    }
}
