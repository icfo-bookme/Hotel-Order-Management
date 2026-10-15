<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountService
{
    public function register(array $data): User
    {
        $user = User::create($data);
        event(new Registered($user));
        Auth::login($user);

        return $user;
    }

    public function update(User $user, array $data): void
    {
        $user->update($data);
    }

    public function delete(Request $request): void
    {
        $user = $request->user();
        $user->delete();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
