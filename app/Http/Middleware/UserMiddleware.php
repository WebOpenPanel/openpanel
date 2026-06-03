<?php

namespace App\Http\Middleware;

use App\Models\LinuxAuthUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserMiddleware
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

        $port = $request->getPort();
        $adminPorts = [2086, 2087];

        if (!$user->isAdmin() && in_array($port, $adminPorts)) {
            return redirect('https://' . $request->getHost() . ':2083' . $request->getRequestUri());
        }

        return $next($request);
    }
}
