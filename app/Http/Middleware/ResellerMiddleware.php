<?php

namespace App\Http\Middleware;

use App\Models\LinuxAuthUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResellerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user instanceof LinuxAuthUser) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login');
        }

        if (!$user->isAdmin() && !$user->isReseller()) {
            abort(403, 'Reseller or admin access required.');
        }

        return $next($request);
    }
}
