<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Services\AuthSessionService;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthSessionService $service)
    {
        $service->login($request, $request->validated());

        if (!$request->user()->can('dashboard.view')) {
            return redirect()->route('access.home');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(LogoutRequest $request, AuthSessionService $service)
    {
        $service->logout($request);

        return redirect('/');
    }
}
