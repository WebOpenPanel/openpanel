<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeparateSessionPerPort
{
    public function handle(Request $request, Closure $next): Response
    {
        $port = $request->getPort();
        $baseCookie = config('session.cookie');

        if ($port === 2083) {
            config(['session.cookie' => $baseCookie . '_user']);
        } else {
            config(['session.cookie' => $baseCookie . '_admin']);
        }

        return $next($request);
    }
}
