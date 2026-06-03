<?php
/**
 * OpenPanel WHMCS Server Module
 *
 * Drop this folder into: WHMCS_DIR/modules/servers/openpanel/
 * Then configure a new server in WHMCS with module "OpenPanel".
 */

if (!defined("WHMCS")) die("WHMCS not loaded");

/**
 * Module metadata for WHMCS.
 */
function openpanel_MetaData()
{
    return [
        'DisplayName' => 'OpenPanel Hosting Controller',
        'APIVersion' => '1.0',
        'RequiresServer' => true,
    ];
}

/**
 * Config fields shown in WHMCS server settings.
 */
function openpanel_ConfigOptions()
{
    return [
        'API URL' => ['Type' => 'text', 'Size' => '50', 'Default' => 'https://server.example.com:2087', 'Description' => 'OpenPanel API base URL'],
        'API Token' => ['Type' => 'password', 'Size' => '50', 'Default' => '', 'Description' => 'API token from OpenPanel admin'],
        'Product ID Mapping' => ['Type' => 'text', 'Size' => '20', 'Default' => '', 'Description' => 'OpenPanel billing product ID (optional)'],
        'Auto Install WordPress' => ['Type' => 'yesno', 'Default' => '0', 'Description' => 'Install WordPress on account creation'],
        'Default WordPress Profile' => ['Type' => 'dropdown', 'Options' => 'safe_default,high_traffic,woocommerce,membership,development', 'Default' => 'safe_default'],
        'Enable Redis' => ['Type' => 'yesno', 'Default' => '0', 'Description' => 'Enable Redis object cache for WordPress'],
        'Enable Varnish' => ['Type' => 'yesno', 'Default' => '0', 'Description' => 'Enable Varnish page cache'],
    ];
}

/**
 * Create a hosting account.
 */
function openpanel_CreateAccount(array $params): string
{
    $response = apiRequest($params, 'POST', '/accounts/create', [
        'username' => $params['username'],
        'password' => $params['password'],
        'domain' => $params['domain'],
        'package' => $params['configoption3'] ?? 'default',
        'email' => $params['clientsdetails']['email'] ?? "admin@{$params['domain']}",
    ]);

    if (!$response['success']) {
        return $response['error'] ?? 'Account creation failed';
    }

    // Auto-install WordPress if enabled
    if ($params['configoption4'] === 'on' && !empty($response['username'])) {
        $account = apiRequest($params, 'GET', '/accounts/' . $params['username']);
        if ($account['success'] && !empty($account['account']['id'])) {
            $wpResult = apiRequest($params, 'POST', '/wordpress/install', [
                'user_account_id' => $account['account']['id'],
                'domain' => $params['domain'],
                'site_title' => $params['domain'],
                'admin_user' => 'admin',
                'admin_password' => generatePassword(),
                'admin_email' => $params['clientsdetails']['email'] ?? "admin@{$params['domain']}",
            ]);

            if ($wpResult['success'] && $params['configoption5'] !== 'on') {
                $siteId = $wpResult['site_id'] ?? 0;
                // Apply WordPress profile
                apiRequest($params, 'POST', '/wordpress/apply-profile', [
                    'site_id' => $siteId,
                    'profile' => $params['configoption5'] ?? 'safe_default',
                ]);
            }
        }
    }

    return 'success';
}

/**
 * Suspend a hosting account.
 */
function openpanel_SuspendAccount(array $params): string
{
    $response = apiRequest($params, 'POST', '/accounts/' . $params['username'] . '/suspend');
    return $response['success'] ? 'success' : ($response['error'] ?? 'Suspend failed');
}

/**
 * Unsuspend a hosting account.
 */
function openpanel_UnsuspendAccount(array $params): string
{
    $response = apiRequest($params, 'POST', '/accounts/' . $params['username'] . '/unsuspend');
    return $response['success'] ? 'success' : ($response['error'] ?? 'Unsuspend failed');
}

/**
 * Terminate a hosting account.
 */
function openpanel_TerminateAccount(array $params): string
{
    $response = apiRequest($params, 'POST', '/accounts/' . $params['username'] . '/terminate', [
        'confirm' => true,
    ]);
    return $response['success'] ? 'success' : ($response['error'] ?? 'Terminate failed');
}

/**
 * Change account password.
 */
function openpanel_ChangePassword(array $params): string
{
    $response = apiRequest($params, 'POST', '/accounts/' . $params['username'] . '/change-password', [
        'password' => $params['password'],
    ]);
    return $response['success'] ? 'success' : ($response['error'] ?? 'Password change failed');
}

/**
 * Change account package.
 */
function openpanel_ChangePackage(array $params): string
{
    $response = apiRequest($params, 'POST', '/accounts/' . $params['username'] . '/change-package', [
        'package' => $params['configoption3'] ?? 'default',
    ]);
    return $response['success'] ? 'success' : ($response['error'] ?? 'Package change failed');
}

// ─── Internal Helpers ──────────────────────────────────────────

function apiRequest(array $params, string $method, string $path, array $data = []): array
{
    $url = rtrim($params['configoption1'] ?? '', '/') . '/api/v1' . $path;

    $ch = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . ($params['configoption2'] ?? ''),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true) ?: ['success' => false, 'error' => 'Invalid response'];
    $decoded['_http_code'] = $httpCode;

    return $decoded;
}

function generatePassword(int $length = 16): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
}
