<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Same limit as the session itself (SESSION_LIFETIME), so there is one setting to tune.
            $timeout = (int) config('session.lifetime', 120);
            $lastActivity = session('last_activity_time');
            // Carbon 3 diffs are signed: measure from the last activity forward to now.
            if ($lastActivity && Carbon::parse($lastActivity)->diffInMinutes(now()) >= $timeout) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'For security, you have been logged out after a period of inactivity.');
            }
            session(['last_activity_time' => now()]);
        }

        return $next($request);
    }
}
