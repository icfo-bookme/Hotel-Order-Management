<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordService
{
    public function update(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    public function confirm(Request $request): void
    {
        $request->session()->put('auth.password_confirmed_at', time());
    }

    public function sendResetLink(array $data): string
    {
        return Password::sendResetLink($data);
    }

    public function reset(array $data): string
    {
        return Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
    }
}
