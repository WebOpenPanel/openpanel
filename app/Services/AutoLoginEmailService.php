<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class AutoLoginEmailService
{
    public static function generateAutoLoginToken(string $username): array
    {
        $secret = config('app.key');
        $token = hash_hmac('sha256', $username . '|' . time(), $secret);
        $payload = base64_encode(json_encode([
            'user' => $username,
            'token' => $token,
            'time' => time(),
        ]));

        return [
            'token' => $payload,
            'url' => '/webmail/?_autologin=1&sess=' . urlencode($payload),
        ];
    }

    public static function validateAutoLoginToken(string $token): ?string
    {
        $json = base64_decode($token);
        $data = json_decode($json);
        if (!$data || !isset($data->user) || !isset($data->token)) {
            return null;
        }

        if (time() - ($data->time ?? 0) > 300) {
            return null;
        }

        return $data->user;
    }

    public static function getWebmailUrl(): string
    {
        $host = request()->getHost();
        $isSecure = request()->isSecure();

        return ($isSecure ? 'https' : 'http') . "://{$host}:" . ($isSecure ? '2096' : '2095') . '/webmail/';
    }

    public static function isRoundcubeInstalled(): bool
    {
        return is_dir('/usr/local/openpanel/services/roundcube');
    }
}
