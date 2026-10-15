<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileUpdateRequest $request, AccountService $service)
    {
        $service->update($request->user(), $request->validated());

        return redirect()->route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(DeleteAccountRequest $request, AccountService $service)
    {
        $service->delete($request);

        return redirect('/');
    }
}
