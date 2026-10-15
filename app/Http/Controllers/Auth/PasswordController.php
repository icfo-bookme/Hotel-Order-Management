<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Services\PasswordService;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, PasswordService $service)
    {
        $service->update($request->user(), $request->validated()['password']);

        return back()->with('status', 'password-updated');
    }
}
