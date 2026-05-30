<?php

namespace App\Services;

class FileService
{
    const LOGROTATE_DIR = '/etc/logrotate.d/';
    const LOG_DIR = '/var/log/';

    public static function listDirectory(string $path): array
    {
        $path = rtrim($path, '/');
        if (!is_dir($path)) return [];

        $items = [];
        $entries = scandir($path);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $fullPath = $path . '/' . $entry;
            $stat = @stat($fullPath);
            $items[] = [
                'name' => $entry,
                'path' => $fullPath,
                'is_dir' => is_dir($fullPath),
                'is_file' => is_file($fullPath),
                'is_link' => is_link($fullPath),
                'size' => is_file($fullPath) ? ($stat['size'] ?? 0) : 0,
                'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4),
                'owner' => function_exists('posix_getpwuid') ? (@posix_getpwuid($stat['uid'])['name'] ?? $stat['uid']) : $stat['uid'] ?? 0,
                'group' => function_exists('posix_getgrgid') ? (@posix_getgrgid($stat['gid'])['name'] ?? $stat['gid']) : $stat['gid'] ?? 0,
                'modified' => date('Y-m-d H:i:s', $stat['mtime'] ?? 0),
                'is_readable' => is_readable($fullPath),
                'is_writable' => is_writable($fullPath),
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    public static function readFile(string $path): string
    {
        if (!file_exists($path) || !is_readable($path)) return '';
        $size = filesize($path);
        if ($size > 10 * 1024 * 1024) return '[File too large: ' . ShellService::formatBytes($size) . ']';
        return file_get_contents($path) ?: '';
    }

    public static function writeFile(string $path, string $content): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return file_put_contents($path, $content) !== false;
    }

    public static function deleteFile(string $path, bool $permanent = false): bool
    {
        if (!file_exists($path)) return false;

        if ($permanent) {
            if (is_dir($path)) {
                return self::deleteDirectory($path);
            }
            return unlink($path);
        }

        if (!is_dir(self::TRASH_DIR)) {
            mkdir(self::TRASH_DIR, 0700, true);
        }

        $trashPath = self::TRASH_DIR . '/' . basename($path) . '.' . time();
        if (file_exists($trashPath)) {
            $trashPath .= '_' . rand(1000, 9999);
        }
        return rename($path, $trashPath);
    }

    public static function emptyTrash(): bool
    {
        if (!is_dir(self::TRASH_DIR)) return true;
        return self::deleteDirectory(self::TRASH_DIR) && mkdir(self::TRASH_DIR, 0700, true);
    }

    public static function deleteDirectory(string $path): bool
    {
        if (!is_dir($path)) return false;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }
        return rmdir($path);
    }

    public static function renameFile(string $oldPath, string $newPath): bool
    {
        return rename($oldPath, $newPath);
    }

    public static function copyFile(string $source, string $dest): bool
    {
        if (is_dir($source)) {
            return self::copyDirectory($source, $dest);
        }
        $dir = dirname($dest);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return copy($source, $dest);
    }

    public static function copyDirectory(string $source, string $dest): bool
    {
        if (!is_dir($source)) return false;
        if (!is_dir($dest)) mkdir($dest, 0755, true);
        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $s = $source . '/' . $item;
            $d = $dest . '/' . $item;
            if (is_dir($s)) {
                self::copyDirectory($s, $d);
            } else {
                copy($s, $d);
            }
        }
        return true;
    }

    public static function moveFile(string $source, string $dest): bool
    {
        $dir = dirname($dest);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return rename($source, $dest);
    }

    public static function createDirectory(string $path, int $mode = 0755): bool
    {
        return mkdir($path, $mode, true);
    }

    public static function changePermissions(string $path, string $permissions): bool
    {
        $mode = octdec($permissions);
        return chmod($path, $mode);
    }

    public static function changeOwner(string $path, string $owner, string $group = ''): bool
    {
        $cmd = 'chown ' . escapeshellarg($owner);
        if ($group) $cmd .= ':' . escapeshellarg($group);
        $cmd .= ' ' . escapeshellarg($path);
        ShellService::exec($cmd);
        return true;
    }

    const TRASH_DIR = '/home/.trash';
    const MAX_UPLOAD_SIZE = 104857600;
    const BLOCKED_EXTENSIONS = ['php', 'php3', 'php4', 'php5', 'php6', 'php7', 'pht', 'phps', 'cgi', 'pl', 'asp', 'aspx', 'shtml', 'shtm', 'phtml', 'phtm', 'htm', 'pear', 'ajax', 'config', 'conf', 'htaccess', 'jar', 'exe', 'bat', 'com', 'sh', 'bash'];

    protected static function mimeValidate(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return !in_array($ext, self::BLOCKED_EXTENSIONS);
    }

    protected static function formatPermissions(int $perms): string
    {
        $info = (($perms & 0x1000) ? 't' : '-');
        $info .= (($perms & 0x0400) ? 'r' : '-');
        $info .= (($perms & 0x0200) ? 'w' : '-');
        $info .= (($perms & 0x0100) ? 'x' : '-');
        $info .= (($perms & 0x0040) ? 'r' : '-');
        $info .= (($perms & 0x0020) ? 'w' : '-');
        $info .= (($perms & 0x0010) ? 'x' : '-');
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? 'x' : '-');
        return $info;
    }

    public static function compress(string $path, string $dest = '', string $type = 'tar.gz'): string
    {
        if (empty($dest)) $dest = $path . '.' . $type;
        $dir = dirname($path);
        $base = basename($path);

        return match ($type) {
            'zip' => ShellService::exec("cd " . escapeshellarg($dir) . " && ionice -c2 -n7 nice -n +19 zip -r " . escapeshellarg($dest) . " " . escapeshellarg($base) . " 2>&1"),
            'tar' => ShellService::exec("ionice -c2 -n7 nice -n +19 tar -cf " . escapeshellarg($dest) . " -C " . escapeshellarg($dir) . " " . escapeshellarg($base) . " 2>&1"),
            'tar.bz2' => ShellService::exec("ionice -c2 -n7 nice -n +19 tar -cjf " . escapeshellarg($dest) . " -C " . escapeshellarg($dir) . " " . escapeshellarg($base) . " 2>&1"),
            default => ShellService::exec("ionice -c2 -n7 nice -n +19 tar -czf " . escapeshellarg($dest) . " -C " . escapeshellarg($dir) . " " . escapeshellarg($base) . " 2>&1"),
        };
    }

    public static function extract(string $path, string $dest = ''): string
    {
        if (empty($dest)) $dest = dirname($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $basename = strtolower(basename($path));

        if ($ext === 'zip') {
            return ShellService::exec("ionice -c2 -n7 nice -n +19 unzip -o " . escapeshellarg($path) . " -d " . escapeshellarg($dest) . " 2>&1");
        }
        if ($ext === 'gz' && strpos($basename, '.tar.gz') === false && strpos($basename, '.tgz') === false) {
            return ShellService::exec("ionice -c2 -n7 nice -n +19 gunzip " . escapeshellarg($path) . " 2>&1");
        }
        if (strpos($basename, '.tar.bz2') !== false || $ext === 'bz2') {
            return ShellService::exec("ionice -c2 -n7 nice -n +19 tar -xjf " . escapeshellarg($path) . " -C " . escapeshellarg($dest) . " 2>&1");
        }
        return ShellService::exec("ionice -c2 -n7 nice -n +19 tar -xzf " . escapeshellarg($path) . " -C " . escapeshellarg($dest) . " 2>&1");
    }

    public static function download(string $path): ?string
    {
        if (!file_exists($path) || !is_file($path)) return null;
        return $path;
    }

    public static function upload(string $destDir, string $tmpPath, string $filename): array
    {
        if (!self::mimeValidate($filename)) {
            return ['success' => false, 'error' => 'File type not allowed'];
        }
        if (filesize($tmpPath) > self::MAX_UPLOAD_SIZE) {
            return ['success' => false, 'error' => 'File too large (max 100MB)'];
        }
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $dest = rtrim($destDir, '/') . '/' . $filename;
        if (file_exists($dest)) {
            $dest .= '.' . time();
        }
        $result = move_uploaded_file($tmpPath, $dest);
        return ['success' => $result, 'path' => $dest, 'error' => $result ? null : 'Upload failed'];
    }

    public static function searchFiles(string $path, string $pattern): array
    {
        $results = [];
        $output = ShellService::exec("find " . escapeshellarg($path) . " -name " . escapeshellarg('*' . $pattern . '*') . " -type f 2>/dev/null | head -100");
        foreach (explode("\n", $output) as $file) {
            $file = trim($file);
            if (!empty($file)) {
                $results[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => filesize($file) ?: 0,
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
        }
        return $results;
    }

    public static function grepInFiles(string $path, string $pattern): array
    {
        $results = [];
        $output = ShellService::exec("grep -rn " . escapeshellarg($pattern) . " " . escapeshellarg($path) . " --include='*.php' --include='*.conf' --include='*.txt' --include='*.html' 2>/dev/null | head -100");
        foreach (explode("\n", $output) as $line) {
            if (!empty(trim($line)) && preg_match('/^(.+?):(\d+):(.*)$/', $line, $m)) {
                $results[] = ['file' => $m[1], 'line' => (int) $m[2], 'content' => $m[3]];
            }
        }
        return $results;
    }

    public static function getDiskUsage(string $path = '/home'): array
    {
        $output = ShellService::exec("du -sh " . escapeshellarg($path) . "/* 2>/dev/null | sort -rh | head -20");
        $items = [];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line), 2);
            if (count($parts) >= 2) {
                $items[] = ['size' => $parts[0], 'path' => $parts[1]];
            }
        }
        return $items;
    }

    public static function getDiskDetails(string $path = '/'): array
    {
        $output = ShellService::exec("df -h " . escapeshellarg($path) . " 2>/dev/null");
        $lines = explode("\n", $output);
        $details = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 6) {
                $details[] = [
                    'filesystem' => $parts[0],
                    'size' => $parts[1],
                    'used' => $parts[2],
                    'available' => $parts[3],
                    'percent' => $parts[4],
                    'mount' => $parts[5],
                ];
            }
        }
        return $details;
    }

    public static function getInodeUsage(): array
    {
        $output = ShellService::exec("df -i 2>/dev/null");
        $lines = explode("\n", $output);
        $inodes = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 6 && !empty($parts[0])) {
                $inodes[] = [
                    'filesystem' => $parts[0],
                    'total' => $parts[1],
                    'used' => $parts[2],
                    'free' => $parts[3],
                    'percent' => $parts[4],
                    'mount' => $parts[5],
                ];
            }
        }
        return $inodes;
    }

    // Log viewer
    public static function getLogFiles(string $logDir = ''): array
    {
        $logDir = $logDir ?: self::LOG_DIR;
        $files = [];
        if (!is_dir($logDir)) return $files;
        foreach (ShellService::dirList($logDir) as $file) {
            $fullPath = $logDir . '/' . $file;
            if (is_file($fullPath)) {
                $files[] = [
                    'name' => $file,
                    'path' => $fullPath,
                    'size' => filesize($fullPath),
                    'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                ];
            }
        }
        usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
        return $files;
    }

    public static function tailLog(string $path, int $lines = 100): string
    {
        if (!file_exists($path)) return 'Log file not found';
        return ShellService::exec("tail -n {$lines} " . escapeshellarg($path) . " 2>/dev/null");
    }

    public static function searchLog(string $path, string $pattern, int $lines = 50): string
    {
        if (!file_exists($path)) return 'Log file not found';
        return ShellService::exec("grep -i " . escapeshellarg($pattern) . " " . escapeshellarg($path) . " 2>/dev/null | tail -n {$lines}");
    }

    // Logrotate
    public static function getLogrotateConfigs(): array
    {
        $configs = [];
        if (!is_dir(self::LOGROTATE_DIR)) return $configs;
        foreach (ShellService::dirList(self::LOGROTATE_DIR) as $file) {
            $configs[] = [
                'name' => $file,
                'content' => ShellService::readFile(self::LOGROTATE_DIR . $file),
            ];
        }
        return $configs;
    }

    public static function getLogrotateConf(string $name): string
    {
        return ShellService::readFile(self::LOGROTATE_DIR . $name);
    }

    public static function saveLogrotateConf(string $name, string $content): bool
    {
        return ShellService::writeFile(self::LOGROTATE_DIR . $name, $content);
    }

    public static function runLogrotate(): string
    {
        return ShellService::exec('logrotate -f /etc/logrotate.conf 2>&1');
    }

    public static function getLogrotateMainConf(): string
    {
        return ShellService::readFile('/etc/logrotate.conf');
    }

    public static function getQuotaInfo(string $username): array
    {
        $output = ShellService::exec("quota -u " . escapeshellarg($username) . " 2>/dev/null");
        return ['raw' => $output];
    }

    public static function setQuota(string $username, int $softMb, int $hardMb): bool
    {
        ShellService::exec("setquota -u " . escapeshellarg($username) . " " . ($softMb * 1024) . " " . ($hardMb * 1024) . " 0 0 / 2>&1");
        return true;
    }
}
