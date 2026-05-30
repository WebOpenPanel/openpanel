<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ShellService
{
    public static function exec(string $command, int $timeout = 60): string
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, '/', [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/alt/php*/usr/bin',
        ]);

        if (!is_resource($process)) {
            Log::error("ShellService: Failed to execute command: {$command}");
            return '';
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);

        if (!empty($stderr)) {
            Log::debug("ShellService stderr: {$stderr}");
        }

        return trim($stdout);
    }

    public static function execBackground(string $command): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B " . $command, "r"));
        } else {
            exec($command . " > /dev/null 2>&1 &");
        }
    }

    public static function readFile(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        }
        return file_get_contents($path) ?: '';
    }

    public static function writeFile(string $path, string $content): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($path, $content) !== false;
    }

    public static function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public static function appendFile(string $path, string $content): bool
    {
        return file_put_contents($path, $content, FILE_APPEND | LOCK_EX) !== false;
    }

    public static function replaceInFile(string $path, string $search, string $replace): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $content = file_get_contents($path);
        $content = str_replace($search, $replace, $content);
        return file_put_contents($path, $content) !== false;
    }

    public static function replacePatternInFile(string $path, string $pattern, string $replace): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $content = file_get_contents($path);
        $content = preg_replace($pattern, $replace, $content);
        return file_put_contents($path, $content) !== false;
    }

    public static function deleteLineFromFile(string $path, string $pattern): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $lines = array_filter($lines, fn($line) => !preg_match($pattern, $line));
        return file_put_contents($path, implode("\n", $lines) . "\n") !== false;
    }

    public static function lineExistsInFile(string $path, string $pattern): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $content = file_get_contents($path);
        return (bool) preg_match($pattern, $content);
    }

    public static function addLineToFileIfMissing(string $filePath, string $line, ?string $pattern = null): bool
    {
        $checkPattern = $pattern ?: '/' . preg_quote($line, '/') . '/';
        if (self::lineExistsInFile($filePath, $checkPattern)) {
            return true;
        }
        return self::appendFile($filePath, $line . "\n");
    }

    public static function dirList(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $items = scandir($path);
        return array_filter($items, fn($item) => $item !== '.' && $item !== '..');
    }

    public static function dirSize(string $path): int
    {
        $size = 0;
        if (!is_dir($path)) {
            return 0;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function formatFileSize(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), $precision) . ' ' . $units[$i];
    }

    public static function removeEmptyLines(string $text): string
    {
        return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $text);
    }

    public static function removeNewLines(string $text): string
    {
        return str_replace(["\r", "\n"], '', $text);
    }

    public static function removeEmptySpace(string $text): string
    {
        return preg_replace('/\s+/', ' ', trim($text));
    }
}
