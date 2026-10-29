<?php

use App\Http\Controllers\HotelBookingController;
use App\Http\Controllers\HotelBookingPaymentController;
use App\Http\Controllers\HotelBookingRoomController;
use App\Http\Controllers\HotelOptionController;
use App\Http\Controllers\HotelPayoutController;
use App\Http\Controllers\HotelReportController;
use App\Http\Controllers\HotelSettingController;
use App\Http\Controllers\HotelPaymentAccountController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipTicketSaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/hotel-settings', [HotelSettingController::class, 'index'])->name('hotel-settings.index');
    Route::post('/hotel-settings', [HotelSettingController::class, 'store'])->name('hotel-settings.store');
    Route::put('/hotel-settings', [HotelSettingController::class, 'update'])->name('hotel-settings.update');
    Route::view('/access', 'role_management.access')->name('access.home');
    Route::get('/role-management/users', [\App\Http\Controllers\UserManagementController::class,'index'])->name('role-management.users');
    Route::get('/role-management/users/data', [\App\Http\Controllers\UserManagementController::class,'data'])->name('role-management.users.data');
    Route::post('/role-management/users', [\App\Http\Controllers\UserManagementController::class,'store'])->name('role-management.users.store');
    Route::put('/role-management/users/{user}', [\App\Http\Controllers\UserManagementController::class,'update'])->name('role-management.users.update');
    Route::get('/role-management/roles', [\App\Http\Controllers\RoleManagementController::class,'index'])->name('role-management.roles');
    Route::get('/role-management/roles/data', [\App\Http\Controllers\RoleManagementController::class,'data'])->name('role-management.roles.data');
    Route::get('/role-management/roles/{role}', [\App\Http\Controllers\RoleManagementController::class,'show'])->name('role-management.roles.show');
    Route::post('/role-management/roles', [\App\Http\Controllers\RoleManagementController::class,'store'])->name('role-management.roles.store');
    Route::put('/role-management/roles/{role}', [\App\Http\Controllers\RoleManagementController::class,'update'])->name('role-management.roles.update');
    Route::delete('/role-management/roles/{role}', [\App\Http\Controllers\RoleManagementController::class,'destroy'])->name('role-management.roles.destroy');
    Route::get('/role-management/permissions', [\App\Http\Controllers\PermissionManagementController::class,'index'])->name('role-management.permissions');
    Route::get('/role-management/permissions/data', [\App\Http\Controllers\PermissionManagementController::class,'data'])->name('role-management.permissions.data');

    Route::get('/hotel-bookings/trash', [HotelBookingController::class, 'trash'])->name('hotel-bookings.trash');
    Route::get('/hotel-bookings/trash/data', [HotelBookingController::class, 'trashData'])->name('hotel-bookings.trash.data');
    Route::delete('/hotel-bookings/{hotelBooking}', [HotelBookingController::class, 'destroy'])->name('hotel-bookings.destroy');
    Route::post('/hotel-bookings/{hotelBooking}/restore', [HotelBookingController::class, 'restore'])->withTrashed()->name('hotel-bookings.restore');
    Route::get('/hotel-bookings/{hotelBooking}/invoice', [HotelBookingController::class, 'invoice'])->name('hotel-bookings.invoice');
    Route::get('/hotel/sales/{shipTicketSale}/reschedules', [\App\Http\Controllers\HotelBookingRescheduleController::class, 'index'])->name('hotel-sales.reschedules');
    Route::get('/hotel/sales/{shipTicketSale}/removable-rooms', [\App\Http\Controllers\HotelRoomRemovalController::class, 'index'])->name('hotel-sales.removable-rooms');
    Route::get('/hotel/sales/{shipTicketSale}/rooms', [HotelBookingRoomController::class, 'index'])->name('hotel-sales.rooms');
    Route::post('/hotel-booking-rooms/{hotelBookingRoom}/remove', [\App\Http\Controllers\HotelRoomRemovalController::class, 'store'])->name('hotel-booking-rooms.remove');
    Route::post('/hotel-booking-rooms/{hotelBookingRoom}/reschedule', [\App\Http\Controllers\HotelBookingRescheduleController::class, 'store'])->name('hotel-booking-rooms.reschedule');
    Route::get('/reports/hotel-sales', [HotelReportController::class, 'index'])->name('reports.hotel-sales');
    Route::get('/reports/hotel-sales/data', [HotelReportController::class, 'data'])->name('reports.hotel-sales.data');
    Route::get('/reports/hotel-sales/export', [HotelReportController::class, 'export'])->name('reports.hotel-sales.export');
    Route::get('/hotel-payment-accounts/data', [HotelPaymentAccountController::class, 'data'])->name('hotel-payment-accounts.data');
    Route::resource('hotel-payment-accounts', HotelPaymentAccountController::class)->only(['index', 'store', 'update']);
    Route::redirect('/paid-to-hotel', '/hotel-payouts/pending')->name('paid-to-hotel.index');
    Route::get('/hotel-payouts/pending', [HotelPayoutController::class, 'pending'])->name('hotel-payouts.pending');
    Route::get('/hotel-payouts/pending/data', [HotelPayoutController::class, 'data'])->name('hotel-payouts.pending.data');
    Route::get('/hotel-payouts/paid', [HotelPayoutController::class, 'paid'])->name('hotel-payouts.paid');
    Route::get('/hotel-payouts/paid/data', [HotelPayoutController::class, 'paidData'])->name('hotel-payouts.paid.data');
    Route::get('/hotel-payouts/{hotelPayout}/attachment', [HotelPayoutController::class, 'attachment'])->name('hotel-payouts.attachment');
    Route::post('/hotel-payouts/{hotelBookingAdvance}', [HotelPayoutController::class, 'store'])->name('hotel-payouts.store');
    Route::patch('/hotel-bookings/{hotelBooking}/advances', [HotelPayoutController::class, 'updateAdvances'])->name('hotel-bookings.advances');
    Route::post('/hotel/sales/{shipTicketSale}/verify-payments', [HotelBookingPaymentController::class, 'verifySale'])->name('hotel-sales.verify-payments');
    Route::patch('/hotel-booking-payments/{hotelBookingPayment}/review', [HotelBookingPaymentController::class, 'review'])->name('hotel-booking-payments.review');
    Route::get('/hotel-options', [HotelOptionController::class, 'index'])->name('hotel-options.index');
    Route::get('/hotel-options/{hotelId}/rooms', [HotelOptionController::class, 'rooms'])->whereNumber('hotelId')->name('hotel-options.rooms');
    Route::get('/hotel-booking-payments/{hotelBookingPayment}/attachment', [HotelBookingPaymentController::class, 'attachment'])->name('hotel-booking-payments.attachment');
    Route::apiResource('hotel-bookings', HotelBookingController::class)->except('destroy');
    Route::apiResource('hotel-booking-rooms', HotelBookingRoomController::class)->only(['store', 'show', 'update']);
    Route::apiResource('hotel-booking-payments', HotelBookingPaymentController::class)->only(['store', 'show', 'update']);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/hotel/sales/{shipTicketSale}/sell', [ShipTicketSaleController::class, 'sell'])->name('hotel-sales.sell');
    Route::get('/hotel/{status}', [ShipTicketSaleController::class, 'index'])->name('ship-tickets.index');
    Route::get('/ship-tickets/data/{status}', [ShipTicketSaleController::class, 'getData'])->name('ship-tickets.data');
    Route::post('/save-remarks', [ShipTicketSaleController::class, 'store'])->name('remarks.save');
    Route::post('/update-remarks', [ShipTicketSaleController::class, 'update'])->name('remarks.update');
    Route::get('/dashboard', [ShipTicketSaleController::class, 'dashboard'])->name('dashboard');
});

require __DIR__.'/auth.php';

Route::get('/', function () {
    return auth()->check() ? redirect()->route(auth()->user()->can('dashboard.view') ? 'dashboard' : 'access.home') : view('auth.login');
});
