<?php

namespace App\Services;

class RootPasswordService
{
    public static function changePassword(string $newPassword): array
    {
        $escaped = escapeshellarg($newPassword . "\n" . $newPassword);
        $output = ShellService::exec("echo -e {$escaped} | /usr/bin/passwd root 2>&1");
        $success = stripos($output, 'success') !== false || stripos($output, 'all authentication tokens updated') !== false;
        return ['success' => $success, 'output' => $output];
    }
}
