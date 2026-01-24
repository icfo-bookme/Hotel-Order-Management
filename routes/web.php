<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipTicketSaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard'); 

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function() {
    Route::get('/hotel/{status}', [ShipTicketSaleController::class, 'index'])->name('ship-tickets.index');
    Route::get('/ship-tickets/data/{status}', [ShipTicketSaleController::class, 'getData'])->name('ship-tickets.data');
    Route::post('/save-remarks', [ShipTicketSaleController::class, 'store'])->name('remarks.save');
    Route::post('/update-remarks', [ShipTicketSaleController::class, 'update'])->name('remarks.update');
});

require __DIR__.'/auth.php';
