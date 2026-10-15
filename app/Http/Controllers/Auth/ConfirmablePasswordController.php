<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPasswordRequest;
use App\Services\PasswordService;

class ConfirmablePasswordController extends Controller
{
    public function show()
    {
        return view('auth.confirm-password');
    }

    public function store(ConfirmPasswordRequest $request, PasswordService $service)
    {
        $service->confirm($request);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
