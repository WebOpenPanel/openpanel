<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Auth::check() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => $request->method(),
                'module' => $request->route()?->getName() ?? $request->path(),
                'description' => $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
            ]);
        }

        return $response;
    }
}
