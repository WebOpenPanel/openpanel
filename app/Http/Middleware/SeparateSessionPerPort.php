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

        $userPorts = [2082, 2083, 2095, 2096];
        $adminPorts = [2086, 2087];

        if (in_array($port, $userPorts)) {
            config(['session.cookie' => $baseCookie . '_user']);
        } elseif (in_array($port, $adminPorts)) {
            config(['session.cookie' => $baseCookie . '_admin']);
        } else {
            config(['session.cookie' => $baseCookie . '_admin']);
        }

        return $next($request);
    }
}
