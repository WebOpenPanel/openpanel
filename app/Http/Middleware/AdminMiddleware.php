<?php

namespace App\Http\Middleware;

use App\Models\LinuxAuthUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user instanceof LinuxAuthUser || !$user->isAdmin()) {
            abort(403, 'Access denied. Root or sudo user required.');
        }

        return $next($request);
    }
}
