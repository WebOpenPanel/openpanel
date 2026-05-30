<?php

namespace App\Services;

class PeclService
{
    public static function getAvailableExtensions(): array
    {
        $output = ShellService::exec('pecl list-channels 2>/dev/null || pecl list 2>/dev/null');
        $extensions = [];
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && !str_starts_with($line, 'Registered') && !str_starts_with($line, '---') && !str_starts_with($line, 'Package')) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 1 && $parts[0]) {
                    $extensions[] = $parts[0];
                }
            }
        }
        return $extensions;
    }

    public static function getInstalledExtensions(string $phpVersion = '8.3'): array
    {
        $phpBin = self::getPhpBin($phpVersion);
        $extDir = trim(ShellService::exec("{$phpBin} -i 2>/dev/null | grep 'extension_dir' | head -1 | awk -F'=>' '{print \$2}' | trim"));
        if (!$extDir || !is_dir($extDir)) {
            $extDir = trim(ShellService::exec("php-config --extension-dir 2>/dev/null"));
        }

        $extensions = [];
        if ($extDir && is_dir($extDir)) {
            $files = glob($extDir . '/*.so');
            foreach ($files as $file) {
                $name = basename($file, '.so');
                $extensions[] = [
                    'name' => $name,
                    'file' => $file,
                    'active' => self::isActive($name, $phpVersion),
                ];
            }
        }
        return $extensions;
    }

    public static function install(string $extension, string $phpVersion = '8.3'): array
    {
        $phpize = self::getPhpize($phpVersion);
        $output = ShellService::exec("{$phpize} && pecl install {$extension} 2>&1");
        $success = stripos($output, 'install ok') !== false || stripos($output, 'successfully') !== false;
        return ['success' => $success, 'output' => $output];
    }

    public static function uninstall(string $extension, string $phpVersion = '8.3'): array
    {
        $output = ShellService::exec("pecl uninstall {$extension} 2>&1");
        return ['success' => stripos($output, 'uninstall ok') !== false || stripos($output, 'not installed') === false, 'output' => $output];
    }

    public static function enable(string $extension, string $phpVersion = '8.3'): array
    {
        $iniPath = self::getIniPath($phpVersion);
        $extLine = "extension={$extension}.so";
        $content = file_exists($iniPath) ? file_get_contents($iniPath) : '';
        if (stripos($content, "extension={$extension}") === false) {
            $content .= "\n{$extLine}\n";
            file_put_contents($iniPath, $content);
        }
        return ['success' => true, 'message' => "{$extension} enabled."];
    }

    public static function disable(string $extension, string $phpVersion = '8.3'): array
    {
        $iniPath = self::getIniPath($phpVersion);
        if (!file_exists($iniPath)) {
            return ['success' => false, 'message' => 'INI file not found.'];
        }
        $content = file_get_contents($iniPath);
        $content = preg_replace("/^;?extension={$extension}\.so\s*$/m", '', $content);
        file_put_contents($iniPath, $content);
        return ['success' => true, 'message' => "{$extension} disabled."];
    }

    public static function search(string $query): array
    {
        $output = ShellService::exec("pecl search {$query} 2>&1");
        $results = [];
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (preg_match('/^(\S+)\s+-\s+(.+)$/', trim($line), $m)) {
                $results[] = ['name' => $m[1], 'description' => trim($m[2])];
            }
        }
        return $results;
    }

    protected static function isActive(string $extension, string $phpVersion): bool
    {
        $phpBin = self::getPhpBin($phpVersion);
        $output = ShellService::exec("{$phpBin} -m 2>/dev/null | grep -i '^{$extension}$'");
        return !empty(trim($output));
    }

    protected static function getPhpBin(string $phpVersion): string
    {
        $path = "/usr/local/openpanel/php/php{$phpVersion}/bin/php";
        return file_exists($path) ? $path : 'php';
    }

    protected static function getPhpize(string $phpVersion): string
    {
        $path = "/usr/local/openpanel/php/php{$phpVersion}/bin/phpize";
        return file_exists($path) ? $path : 'phpize';
    }

    protected static function getIniPath(string $phpVersion): string
    {
        return "/etc/php.d/99-pecl-{$phpVersion}.ini";
    }
}
