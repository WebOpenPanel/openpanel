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
        $cookieName = config('session.cookie');

        if ($port === 2083) {
            config(['session.cookie' => $cookieName . '_user']);
        }

        return $next($request);
    }
}
