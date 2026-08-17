<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Older accounts created before the status column was introduced have
        // a null status. Treat them as active; only an explicit lock disables access.
        if (in_array($request->user()?->status, ['LOCKED', 'INACTIVE'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Tài khoản đã bị khóa hoặc ngừng hoạt động.');
        }

        return $next($request);
    }
}
