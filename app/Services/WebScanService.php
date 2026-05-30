<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class WebScanService
{
    protected static string $scanResultsDir = '/usr/local/openpanel/.conf/scans';

    public static function scanDomain(string $domain, string $docRoot = ''): array
    {
        if (!$docRoot) {
            $docRoot = "/home/{$domain}/public_html";
        }

        $results = [
            'domain' => $domain,
            'doc_root' => $docRoot,
            'scanned_at' => date('Y-m-d H:i:s'),
            'issues' => [],
            'score' => 100,
        ];

        if (!is_dir($docRoot)) {
            return ['success' => false, 'message' => "Document root {$docRoot} does not exist."];
        }

        $results['issues'] = array_merge(
            self::checkFilePermissions($docRoot),
            self::checkSensitiveFiles($docRoot),
            self::checkPhpVulnerabilities($docRoot),
            self::checkOutdatedSoftware($docRoot)
        );

        $results['score'] = max(0, 100 - (count($results['issues']) * 5));

        self::saveResults($domain, $results);

        return ['success' => true, 'results' => $results];
    }

    public static function getScanResults(string $domain): ?array
    {
        $path = self::$scanResultsDir . '/' . str_replace(['.', '/'], '_', $domain) . '.json';
        if (!file_exists($path)) {
            return null;
        }
        return json_decode(file_get_contents($path), true);
    }

    public static function getScanHistory(): array
    {
        if (!is_dir(self::$scanResultsDir)) {
            return [];
        }
        $scans = [];
        foreach (File::files(self::$scanResultsDir) as $file) {
            if ($file->getExtension() === 'json') {
                $data = json_decode(file_get_contents($file->getPathname()), true);
                if ($data) {
                    $scans[] = [
                        'domain' => $data['domain'] ?? 'unknown',
                        'score' => $data['score'] ?? 0,
                        'issues' => count($data['issues'] ?? []),
                        'scanned_at' => $data['scanned_at'] ?? '',
                    ];
                }
            }
        }
        usort($scans, fn($a, $b) => strtotime($b['scanned_at']) - strtotime($a['scanned_at']));
        return $scans;
    }

    protected static function checkFilePermissions(string $docRoot): array
    {
        $issues = [];
        $output = ShellService::exec("find {$docRoot} -type f -perm /o+w 2>/dev/null | head -20");
        $worldWritable = array_filter(explode("\n", trim($output)));
        if (!empty($worldWritable)) {
            $issues[] = [
                'severity' => 'high',
                'type' => 'permissions',
                'title' => 'World-writable files found',
                'description' => count($worldWritable) . ' files are world-writable',
                'files' => array_slice($worldWritable, 0, 5),
            ];
        }

        $output = ShellService::exec("find {$docRoot} -type d -perm /o+w ! -name 'uploads' ! -name 'cache' 2>/dev/null | head -10");
        $worldWritableDirs = array_filter(explode("\n", trim($output)));
        if (!empty($worldWritableDirs)) {
            $issues[] = [
                'severity' => 'medium',
                'type' => 'permissions',
                'title' => 'World-writable directories found',
                'description' => count($worldWritableDirs) . ' directories are world-writable',
                'files' => array_slice($worldWritableDirs, 0, 5),
            ];
        }

        return $issues;
    }

    protected static function checkSensitiveFiles(string $docRoot): array
    {
        $issues = [];
        $sensitiveFiles = [
            '.env' => 'high',
            '.git/config' => 'critical',
            '.htaccess' => 'info',
            'wp-config.php' => 'high',
            'configuration.php' => 'high',
            'config.php' => 'medium',
            'phpinfo.php' => 'high',
            'info.php' => 'high',
            'test.php' => 'medium',
            'backup.sql' => 'critical',
            'dump.sql' => 'critical',
            '.sql' => 'high',
        ];

        foreach ($sensitiveFiles as $file => $severity) {
            $output = ShellService::exec("find {$docRoot} -name '{$file}' -type f 2>/dev/null | head -5");
            $found = array_filter(explode("\n", trim($output)));
            if (!empty($found)) {
                $issues[] = [
                    'severity' => $severity,
                    'type' => 'sensitive_file',
                    'title' => "Sensitive file found: {$file}",
                    'description' => count($found) . " instance(s) of {$file} found",
                    'files' => $found,
                ];
            }
        }

        return $issues;
    }

    protected static function checkPhpVulnerabilities(string $docRoot): array
    {
        $issues = [];

        $output = ShellService::exec("grep -rl 'eval\s*(' {$docRoot} --include='*.php' 2>/dev/null | head -10");
        $evalFiles = array_filter(explode("\n", trim($output)));
        if (!empty($evalFiles)) {
            $issues[] = [
                'severity' => 'medium',
                'type' => 'code',
                'title' => 'eval() usage detected',
                'description' => count($evalFiles) . ' files use eval()',
                'files' => array_slice($evalFiles, 0, 5),
            ];
        }

        $output = ShellService::exec("grep -rl 'base64_decode\s*(' {$docRoot} --include='*.php' 2>/dev/null | head -10");
        $b64Files = array_filter(explode("\n", trim($output)));
        if (!empty($b64Files)) {
            $issues[] = [
                'severity' => 'warning',
                'type' => 'code',
                'title' => 'base64_decode() usage detected',
                'description' => count($b64Files) . ' files use base64_decode() (possible obfuscation)',
                'files' => array_slice($b64Files, 0, 5),
            ];
        }

        return $issues;
    }

    protected static function checkOutdatedSoftware(string $docRoot): array
    {
        $issues = [];

        $versionFile = $docRoot . '/wp-includes/version.php';
        if (file_exists($versionFile)) {
            $content = file_get_contents($versionFile);
            if (preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', $content, $m)) {
                $issues[] = [
                    'severity' => 'info',
                    'type' => 'software',
                    'title' => 'WordPress detected',
                    'description' => "WordPress version: {$m[1]}",
                ];
            }
        }

        return $issues;
    }

    protected static function saveResults(string $domain, array $results): void
    {
        File::ensureDirectoryExists(self::$scanResultsDir);
        $filename = str_replace(['.', '/'], '_', $domain) . '.json';
        file_put_contents(self::$scanResultsDir . '/' . $filename, json_encode($results, JSON_PRETTY_PRINT));
    }
}
