<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $tokenString = $request->bearerToken();

        if (!$tokenString) {
            return $this->jsonError('Missing Authorization header', 401);
        }

        $hashed = ApiToken::hashToken($tokenString);
        $token = ApiToken::where('token', $hashed)->first();

        if (!$token) {
            return $this->jsonError('Invalid API token', 401);
        }

        if (!$token->is_active) {
            return $this->jsonError('API token is revoked', 403);
        }

        if ($token->isExpired()) {
            return $this->jsonError('API token has expired', 403);
        }

        $clientIp = $request->ip();
        if (!$token->isIpAllowed($clientIp)) {
            return $this->jsonError('IP not allowed for this token', 403);
        }

        // Rate limit: 60 req/min per token
        $limiterKey = 'api:' . $token->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 60)) {
            return $this->jsonError('Rate limit exceeded. Max 60 requests per minute.', 429);
        }
        RateLimiter::hit($limiterKey, 60);

        $token->touchLastUsed();

        // Attach token to request for controllers
        $request->merge(['_api_token' => $token]);

        $response = $next($request);

        // Log request (no secrets)
        $duration = (microtime(true) - $startTime) * 1000;
        $this->logRequest($token, $request, $response, $duration);

        return $response;
    }

    private function logRequest(ApiToken $token, Request $request, Response $response, float $duration): void
    {
        try {
            $paramKeys = array_keys($request->except(['_token', '_api_token']));
            ApiRequestLog::create([
                'token_id' => $token->id,
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round($duration, 2),
                'action' => $request->route()->getName() ?? $request->path(),
                'params_summary' => implode(',', $paramKeys),
                'error' => $response->getStatusCode() >= 400 ? $response->getContent() : null,
            ]);
        } catch (\Exception $e) {
            // Don't let logging failures break the API
        }
    }

    private function jsonError(string $message, int $code): Response
    {
        return response()->json([
            'success' => false,
            'error' => $message,
        ], $code);
    }
}
