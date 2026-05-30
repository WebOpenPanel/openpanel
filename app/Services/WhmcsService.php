<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WhmcsService
{
    public static function isConfigured(): bool
    {
        $settings = self::getSettings();
        return !empty($settings['whmcs_url'] ?? '');
    }

    public static function getSettings(): array
    {
        try {
            $row = DB::table('settings')->where('id', 1)->first();
            return $row ? (array) $row : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function saveSettings(array $data): bool
    {
        try {
            DB::table('settings')->where('id', 1)->update($data);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function testConnection(): array
    {
        $settings = self::getSettings();
        $url = rtrim($settings['whmcs_url'] ?? '', '/');
        if (empty($url)) return ['success' => false, 'message' => 'WHMCS URL not configured'];
        $ch = curl_init($url . '/admin/api.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['success' => $httpCode > 0, 'http_code' => $httpCode, 'message' => $httpCode > 0 ? 'Connected' : 'Connection failed'];
    }
}
