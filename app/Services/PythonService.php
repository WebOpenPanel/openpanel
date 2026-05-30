<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class PythonService
{
    protected static string $pythonVersionsFile = '/usr/local/openpanel/.conf/python_versions.json';
    protected static string $pythonPath = '/usr/local/openpanel/python';

    public static function getInstalledVersions(): array
    {
        $versions = [];
        if (!is_dir(self::$pythonPath)) {
            return $versions;
        }
        $dirs = File::directories(self::$pythonPath);
        foreach ($dirs as $dir) {
            $name = basename($dir);
            if (preg_match('/^python([\d.]+)$/', $name, $m)) {
                $binary = $dir . '/bin/python3';
                $versions[] = [
                    'version' => $m[1],
                    'path' => $binary,
                    'installed' => file_exists($binary),
                ];
            }
        }
        usort($versions, fn($a, $b) => version_compare($b['version'], $a['version']));
        return $versions;
    }

    public static function getSystemVersions(): array
    {
        $output = ShellService::exec('python3 --version 2>&1');
        $versions = [];
        if (preg_match('/Python\s+([\d.]+)/', $output, $m)) {
            $versions[] = ['version' => $m[1], 'path' => trim(ShellService::exec('which python3')), 'system' => true];
        }
        $py2 = ShellService::exec('python2 --version 2>&1');
        if (preg_match('/Python\s+([\d.]+)/', $py2, $m)) {
            $versions[] = ['version' => $m[1], 'path' => trim(ShellService::exec('which python2')), 'system' => true];
        }
        return $versions;
    }

    public static function installVersion(string $version): array
    {
        $sanitized = preg_replace('/[^0-9.]/', '', $version);
        $installPath = self::$pythonPath . '/python' . $sanitized;

        if (is_dir($installPath)) {
            return ['success' => false, 'message' => "Python {$sanitized} is already installed."];
        }

        File::ensureDirectoryExists(self::$pythonPath);

        $major = explode('.', $sanitized)[0] ?? '3';
        if ((int)$major < 3) {
            return ['success' => false, 'message' => 'Only Python 3.x versions are supported.'];
        }

        $output = ShellService::exec("dnf -y install python{$major} 2>&1");

        $systemPath = trim(ShellService::exec("which python{$major} 2>/dev/null"));
        if ($systemPath) {
            File::ensureDirectoryExists($installPath . '/bin');
            ShellService::exec("ln -sf {$systemPath} {$installPath}/bin/python3");
            ShellService::exec("ln -sf {$systemPath} {$installPath}/bin/python");
            return ['success' => true, 'message' => "Python {$sanitized} installed.", 'output' => $output];
        }

        return ['success' => false, 'message' => 'Installation failed.', 'output' => $output];
    }

    public static function removeVersion(string $version): array
    {
        $sanitized = preg_replace('/[^0-9.]/', '', $version);
        $installPath = self::$pythonPath . '/python' . $sanitized;

        if (!is_dir($installPath)) {
            return ['success' => false, 'message' => "Python {$sanitized} is not installed."];
        }

        File::deleteDirectory($installPath);
        return ['success' => true, 'message' => "Python {$sanitized} removed."];
    }

    public static function setUserVersion(string $user, string $version): array
    {
        $homeDir = '/home/' . $user;
        if (!is_dir($homeDir)) {
            return ['success' => false, 'message' => "User {$user} not found."];
        }

        $sanitized = preg_replace('/[^0-9.]/', '', $version);
        $pythonBin = self::$pythonPath . '/python' . $sanitized . '/bin/python3';

        if (!file_exists($pythonBin)) {
            $pythonBin = trim(ShellService::exec("which python3 2>/dev/null"));
        }

        $bashrc = $homeDir . '/.bashrc';
        $pyLine = "export PYTHON={$pythonBin}\n";
        $pathLine = 'export PATH="' . dirname($pythonBin) . ':$PATH"' . "\n";

        $content = file_exists($bashrc) ? file_get_contents($bashrc) : '';
        $content = preg_replace('/export PYTHON=.+\n/', '', $content);
        $content = preg_replace('/export PATH=".*python.*:\$PATH"\n/', '', $content);
        $content .= "\n# Python version set by OpenPanel\n{$pyLine}{$pathLine}";

        file_put_contents($bashrc, $content);

        return ['success' => true, 'message' => "Python {$version} set for {$user}."];
    }

    public static function getUserVersion(string $user): ?string
    {
        $homeDir = '/home/' . $user;
        $bashrc = $homeDir . '/.bashrc';
        if (!file_exists($bashrc)) {
            return null;
        }
        $content = file_get_contents($bashrc);
        if (preg_match('/export PYTHON=.+python([\d.]+)/', $content, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function getAvailablePythons(): array
    {
        return ['3.6', '3.7', '3.8', '3.9', '3.10', '3.11', '3.12', '3.13'];
    }
}
