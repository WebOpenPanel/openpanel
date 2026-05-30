<?php

namespace App\Services;

class PhpService
{
    const PHP_INI_BASE = '/usr/local/lib/php.ini';
    const PHP_FPM_BASE = '/usr/local/php-fpm/etc/php-fpm.conf';
    const PHP_INI_DIR = '/usr/local/lib/';
    const PHP_FPM_DIR = '/usr/local/php-fpm/etc/';
    const PHP_BIN_DIR = '/usr/local/bin/';
    const PECL_DIR = '/usr/local/lib/php/extensions/';

    const VERSIONS = ['5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];

    public static function getInstalledVersions(): array
    {
        $versions = [];
        foreach (self::VERSIONS as $v) {
            $bin = '/usr/local/php' . str_replace('.', '', $v) . '/bin/php';
            if (file_exists($bin)) {
                $version = trim(ShellService::exec($bin . ' -v 2>/dev/null | head -1'));
                $versions[] = ['version' => $v, 'binary' => $bin, 'info' => $version];
            }
        }
        $defaultBin = trim(ShellService::exec('which php 2>/dev/null'));
        $defaultVersion = trim(ShellService::exec('php -r "echo PHP_VERSION;" 2>/dev/null'));
        if (!empty($defaultVersion)) {
            $versions[] = ['version' => 'default (' . $defaultVersion . ')', 'binary' => $defaultBin, 'info' => 'PHP ' . $defaultVersion];
        }
        return $versions;
    }

    public static function getDefaultVersion(): string
    {
        return trim(ShellService::exec('php -r "echo PHP_VERSION;" 2>/dev/null'));
    }

    public static function setDefaultCli(string $version): string
    {
        $versionNum = str_replace('php', '', $version);
        $bin = '/usr/local/php' . str_replace('.', '', $versionNum) . '/bin/php';
        if (!file_exists($bin)) {
            $bin = '/opt/alt/php' . str_replace('.', '', $versionNum) . '/usr/bin/php';
        }
        return ShellService::exec("alternatives --set php " . escapeshellarg($bin) . " 2>&1 || ln -sf " . escapeshellarg($bin) . " /usr/local/bin/php 2>&1");
    }

    public static function getPhpInfo(?string $version = null): string
    {
        $bin = $version ? self::getPhpBin($version) : 'php';
        return ShellService::exec($bin . ' -i 2>/dev/null');
    }

    public static function getPhpShortInfo(?string $version = null): string
    {
        $bin = $version ? self::getPhpBin($version) : 'php';
        return ShellService::exec($bin . ' -v 2>/dev/null');
    }

    public static function getPhpIni(?string $version = null): string
    {
        $path = $version ? self::getPhpIniPath($version) : self::PHP_INI_BASE;
        return ShellService::readFile($path);
    }

    public static function savePhpIni(string $content, ?string $version = null): bool
    {
        $path = $version ? self::getPhpIniPath($version) : self::PHP_INI_BASE;
        ShellService::writeFile($path, $content);
        ServerService::serviceAction('restart', 'php-fpm');
        return true;
    }

    public static function getPhpIniSetting(string $key, ?string $version = null): string
    {
        $ini = self::getPhpIni($version);
        if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.+)$/m', $ini, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    public static function setPhpIniSetting(string $key, string $value, ?string $version = null): bool
    {
        $path = $version ? self::getPhpIniPath($version) : self::PHP_INI_BASE;
        ShellService::replacePatternInFile($path, '/^' . preg_quote($key, '/') . '\s*=.*/', $key . ' = ' . $value);
        ServerService::serviceAction('restart', 'php-fpm');
        return true;
    }

    public static function getPhpFpmPools(?string $version = null): array
    {
        $dir = $version ? self::getPhpFpmDir($version) . 'pool.d/' : '/usr/local/php-fpm/etc/pool.d/';
        $pools = [];
        if (!is_dir($dir)) return $pools;
        foreach (ShellService::dirList($dir) as $file) {
            if (preg_match('/\.conf$/', $file)) {
                $content = ShellService::readFile($dir . $file);
                preg_match('/\[([^\]]+)\]/', $content, $m);
                $poolName = $m[1] ?? str_replace('.conf', '', $file);
                $pools[] = [
                    'name' => $poolName,
                    'file' => $file,
                    'content' => $content,
                ];
            }
        }
        return $pools;
    }

    public static function getPhpFpmConf(?string $version = null): string
    {
        $path = $version ? self::getPhpFpmDir($version) . 'php-fpm.conf' : self::PHP_FPM_BASE;
        return ShellService::readFile($path);
    }

    public static function savePhpFpmConf(string $content, ?string $version = null): bool
    {
        $path = $version ? self::getPhpFpmDir($version) . 'php-fpm.conf' : self::PHP_FPM_BASE;
        ShellService::writeFile($path, $content);
        ServerService::serviceAction('restart', 'php-fpm');
        return true;
    }

    public static function getPhpFpmPoolConf(string $pool, ?string $version = null): string
    {
        $dir = $version ? self::getPhpFpmDir($version) . 'pool.d/' : '/usr/local/php-fpm/etc/pool.d/';
        return ShellService::readFile($dir . $pool . '.conf');
    }

    public static function savePhpFpmPoolConf(string $pool, string $content, ?string $version = null): bool
    {
        $dir = $version ? self::getPhpFpmDir($version) . 'pool.d/' : '/usr/local/php-fpm/etc/pool.d/';
        ShellService::writeFile($dir . $pool . '.conf', $content);
        ServerService::serviceAction('restart', 'php-fpm');
        return true;
    }

    public static function getPeclExtensions(?string $version = null): array
    {
        $bin = $version ? self::getPeclBin($version) : 'pecl';
        $output = ShellService::exec($bin . ' list 2>/dev/null');
        $extensions = [];
        $lines = explode("\n", $output);
        foreach ($lines as $i => $line) {
            if ($i < 3) continue;
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                $extensions[] = ['name' => $parts[0], 'version' => $parts[1], 'status' => $parts[2] ?? 'stable'];
            }
        }
        return $extensions;
    }

    public static function getAvailablePeclExtensions(?string $version = null): array
    {
        $bin = $version ? self::getPeclBin($version) : 'pecl';
        $output = ShellService::exec($bin . ' list-all 2>/dev/null');
        $extensions = [];
        $lines = explode("\n", $output);
        foreach ($lines as $i => $line) {
            if ($i < 3) continue;
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                $extensions[] = ['name' => $parts[0], 'version' => $parts[1], 'state' => $parts[2] ?? ''];
            }
        }
        return $extensions;
    }

    public static function peclInstall(string $extension, ?string $version = null): string
    {
        $bin = $version ? self::getPeclBin($version) : 'pecl';
        return ShellService::exec("echo '' | " . $bin . " install " . escapeshellarg($extension) . " 2>&1");
    }

    public static function peclUninstall(string $extension, ?string $version = null): string
    {
        $bin = $version ? self::getPeclBin($version) : 'pecl';
        return ShellService::exec($bin . " uninstall " . escapeshellarg($extension) . " 2>&1");
    }

    public static function installFfmpeg(): string
    {
        $output = ShellService::exec('yum -y install ffmpeg ffmpeg-devel 2>&1');
        $phpVersion = self::getDefaultVersion();
        $peclBin = 'pecl';
        $output .= ShellService::exec("{$peclBin} install ffmpeg 2>&1");
        return $output;
    }

    public static function getFfmpegStatus(): array
    {
        $installed = !empty(ShellService::exec('which ffmpeg 2>/dev/null'));
        $version = $installed ? ShellService::exec('ffmpeg -version 2>/dev/null | head -1') : '';
        return ['installed' => $installed, 'version' => $version];
    }

    private static function getPhpBin(string $version): string
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $bin = '/usr/local/php' . $versionNum . '/bin/php';
        if (!file_exists($bin)) {
            $bin = '/opt/alt/php' . $versionNum . '/usr/bin/php';
        }
        return file_exists($bin) ? $bin : 'php';
    }

    private static function getPhpIniPath(string $version): string
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $path = '/usr/local/php' . $versionNum . '/lib/php.ini';
        if (!file_exists($path)) {
            $path = '/opt/alt/php' . $versionNum . '/etc/php.ini';
        }
        return file_exists($path) ? $path : self::PHP_INI_BASE;
    }

    private static function getPhpFpmDir(string $version): string
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        return '/usr/local/php' . $versionNum . '/etc/';
    }

    private static function getPeclBin(string $version): string
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $bin = '/usr/local/php' . $versionNum . '/bin/pecl';
        if (!file_exists($bin)) {
            $bin = '/opt/alt/php' . $versionNum . '/usr/bin/pecl';
        }
        return file_exists($bin) ? $bin : 'pecl';
    }

    // Per-version PHP-FPM service management (ported from PHPManager)
    public static function getPhpFpmServiceStatus(string $version): array
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $service = "php-fpm{$versionNum}";
        $systemd = ShellService::exec("systemctl is-active {$service} 2>/dev/null");
        $status = trim($systemd);
        if ($status !== 'active') {
            $initd = ShellService::exec("/etc/init.d/{$service} status 2>/dev/null");
            $status = stripos($initd, 'running') !== false ? 'active' : 'inactive';
        }
        return ['version' => $version, 'service' => $service, 'status' => $status];
    }

    public static function phpFpmServiceAction(string $action, string $version): string
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $service = "php-fpm{$versionNum}";
        $output = ServerService::serviceAction($action, $service);
        if (strpos($output, 'not found') !== false || strpos($output, 'Failed') !== false) {
            $output = ShellService::exec("/etc/init.d/{$service} {$action} 2>&1");
        }
        return $output;
    }

    public static function getPhpModules(string $version): array
    {
        $bin = self::getPhpBin($version);
        $output = ShellService::exec($bin . ' -m 2>/dev/null');
        return array_filter(array_map('trim', explode("\n", $output)));
    }

    public static function getPhpStatusDetail(string $version): string
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $service = "php-fpm{$versionNum}";
        return ShellService::exec("systemctl status {$service} 2>&1 || /etc/init.d/{$service} status 2>&1");
    }

    public static function resetPhpOptions(string $version): bool
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $confDir = "/opt/alt/php{$versionNum}/usr/etc/php.d.all/";
        if (!is_dir($confDir)) return false;
        ShellService::exec("rm -f " . escapeshellarg($confDir) . "*.conf 2>/dev/null");
        ServerService::serviceAction('restart', "php-fpm{$versionNum}");
        return true;
    }

    public static function getAutoUpdateStatus(string $version): bool
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $flag = "/opt/alt/php{$versionNum}/.autoupdate";
        return file_exists($flag);
    }

    public static function setAutoUpdate(string $version, bool $enable): bool
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $flag = "/opt/alt/php{$versionNum}/.autoupdate";
        if ($enable) {
            return ShellService::writeFile($flag, date('Y-m-d H:i:s'));
        }
        if (file_exists($flag)) {
            @unlink($flag);
        }
        return true;
    }

    public static function removePhpVersion(string $version): string
    {
        $versionNum = str_replace(['php', '.'], ['', ''], $version);
        $service = "php-fpm{$versionNum}";
        ServerService::serviceAction('stop', $service);
        ShellService::exec("systemctl disable {$service} 2>/dev/null");
        ShellService::exec("rm -f /usr/lib/systemd/system/{$service}.service 2>/dev/null");
        ShellService::exec("rm -f /etc/init.d/{$service} 2>/dev/null");
        ShellService::exec("rm -f /etc/monit.d/{$service}.conf 2>/dev/null");
        ShellService::exec("systemctl daemon-reload 2>/dev/null");
        $dir = "/opt/alt/php{$versionNum}";
        if (is_dir($dir)) {
            ShellService::exec("rm -rf " . escapeshellarg($dir));
        }
        return "PHP {$version} removed.";
    }

    public static function getPhpVersionsMap(): array
    {
        $map = [];
        foreach (self::VERSIONS as $v) {
            $versionNum = str_replace('.', '', $v);
            $bin = "/usr/local/php{$versionNum}/bin/php";
            $altBin = "/opt/alt/php{$versionNum}/usr/bin/php";
            $installed = file_exists($bin) || file_exists($altBin);
            $fpmService = "php-fpm{$versionNum}";
            $fpmActive = trim(ShellService::exec("systemctl is-active {$fpmService} 2>/dev/null")) === 'active';
            $map[] = [
                'version' => $v,
                'installed' => $installed,
                'fpm_active' => $fpmActive,
                'binary' => file_exists($bin) ? $bin : (file_exists($altBin) ? $altBin : null),
            ];
        }
        return $map;
    }
}
