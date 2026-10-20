<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeApplicationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }
        $user = \App\Models\User::with('role.permissions')->find($user->id);
        if (!$user || !$user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $request->expectsJson() ? response()->json(['message' => 'Your account is inactive.'], 401) : redirect()->route('login')->withErrors(['email' => 'Your account is inactive.']);
        }
        Auth::guard('web')->setUser($user);
        $name = $request->route()?->getName();
        $permission = config('access.routes')[$name] ?? null;
        if (in_array($name, ['ship-tickets.index', 'ship-tickets.data'], true)) {
            $permission = ['pending' => 'tickets.pending.view', 'feedbacked' => 'tickets.feedback.view', 'pending-sold' => 'bookings.pending-sold.view', 'sold' => 'bookings.sold.view'][(string) $request->route('status')] ?? null;
            abort_unless($permission, 404);
        }
        if ($name === 'hotel-booking-payments.review') {
            $permission = $request->input('status') === 'rejected' ? 'payments.reject' : 'payments.verify';
        }
        if ($permission) {
            abort_unless($user->canAny((array) $permission), 403, 'You do not have permission to perform this action.');
        }
        return $next($request);
    }
}
