<?php

namespace App\Services;

use App\Models\ApiToken;
use Illuminate\Support\Facades\Cache;

class ApiTokenService
{
    public static function create(array $data): array
    {
        $plainToken = ApiToken::generateToken();
        $hashed = ApiToken::hashToken($plainToken);

        $token = ApiToken::create([
            'name' => $data['name'],
            'token' => $hashed,
            'scopes' => $data['scopes'] ?? ['admin:all'],
            'allowed_ips' => !empty($data['allowed_ips']) ? $data['allowed_ips'] : null,
            'reseller_username' => $data['reseller_username'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return [
            'success' => true,
            'token' => $token,
            'plain_token' => $plainToken, // only shown once
        ];
    }

    public static function revoke(int $tokenId): array
    {
        $token = ApiToken::find($tokenId);
        if (!$token) {
            return ['success' => false, 'message' => 'Token not found'];
        }

        $token->update(['is_active' => false]);
        return ['success' => true, 'message' => "Token '{$token->name}' revoked"];
    }

    public static function reactivate(int $tokenId): array
    {
        $token = ApiToken::find($tokenId);
        if (!$token) {
            return ['success' => false, 'message' => 'Token not found'];
        }

        $token->update(['is_active' => true]);
        return ['success' => true, 'message' => "Token '{$token->name}' reactivated"];
    }

    public static function delete(int $tokenId): array
    {
        $token = ApiToken::find($tokenId);
        if (!$token) {
            return ['success' => false, 'message' => 'Token not found'];
        }

        $token->delete();
        return ['success' => true, 'message' => "Token deleted"];
    }

    public static function list(?string $resellerUsername = null): array
    {
        $query = ApiToken::query();
        if ($resellerUsername !== null) {
            $query->where('reseller_username', $resellerUsername);
        }
        return $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    public static function getRecentLogs(int $limit = 50): array
    {
        return \App\Models\ApiRequestLog::with('token:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public static function checkScope(\App\Models\ApiToken $token, string $scope): bool
    {
        return $token->hasScope($scope);
    }

    public static function enforceScope(\App\Models\ApiToken $token, string $scope): ?\Illuminate\Http\JsonResponse
    {
        if (!$token->hasScope($scope)) {
            return response()->json([
                'success' => false,
                'error' => "Insufficient scope. Required: {$scope}",
            ], 403);
        }
        return null;
    }
}
