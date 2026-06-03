<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ShellService
{
    public static function exec(string $command, int $timeout = 60, bool $audit = true): string
    {
        if ($audit && config('openpanel.security.audit_shell_commands', true)) {
            self::auditLog($command);
        }

        $blocked = config('openpanel.security.blocked_shells', []);
        foreach ($blocked as $pattern) {
            if (stripos($command, $pattern) !== false) {
                Log::warning("ShellService: Blocked dangerous command: {$command}");
                return '';
            }
        }

        $maxLen = config('openpanel.security.max_command_length', 1000);
        if (strlen($command) > $maxLen) {
            Log::warning("ShellService: Command exceeds max length ({$maxLen}): " . substr($command, 0, 100));
            return '';
        }

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

        stream_set_timeout($pipes[1], $timeout);
        stream_set_timeout($pipes[2], $timeout);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if (!empty($stderr)) {
            Log::debug("ShellService stderr (exit={$exitCode}): {$stderr}");
        }

        if ($exitCode !== 0 && $audit) {
            Log::warning("ShellService: Non-zero exit ({$exitCode}): {$command}");
        }

        return trim($stdout);
    }

    public static function run(string $command, int $timeout = 60, bool $audit = true): array
    {
        if ($audit && config('openpanel.security.audit_shell_commands', true)) {
            self::auditLog($command);
        }

        $blocked = config('openpanel.security.blocked_shells', []);
        foreach ($blocked as $pattern) {
            if (stripos($command, $pattern) !== false) {
                Log::warning("ShellService: Blocked dangerous command: {$command}");
                return ['output' => '', 'error' => 'Blocked: dangerous command detected.', 'exit_code' => 1];
            }
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, '/', [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/alt/php*/usr/bin',
        ]);

        if (!is_resource($process)) {
            Log::error("ShellService: Failed to execute: {$command}");
            return ['output' => '', 'error' => 'Failed to start process.', 'exit_code' => -1];
        }

        fclose($pipes[0]);

        stream_set_timeout($pipes[1], $timeout);
        stream_set_timeout($pipes[2], $timeout);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'output' => trim($stdout),
            'error' => trim($stderr),
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
        ];
    }

    public static function execBackground(string $command): void
    {
        self::auditLog($command);
        exec($command . " > /dev/null 2>&1 &");
    }

    protected static function auditLog(string $command): void
    {
        $logFile = config('openpanel.security.log_file', '/var/log/openpanel/audit.log');
        $user = Auth::check() ? Auth::user()->username : 'system';
        $ip = request()->ip() ?? '127.0.0.1';
        $timestamp = date('Y-m-d H:i:s');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($logFile, "[{$timestamp}] [{$user}] [{$ip}] {$command}\n", FILE_APPEND | LOCK_EX);
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

    /**
     * Run a shell command as a specific Linux user (not root).
     *
     * - Validates username against Linux username pattern.
     * - Confirms user exists on the system.
     * - Validates cwd is inside /home/{username} when provided.
     * - Uses sudo -u {username} -H to run as target user.
     * - Sets HOME=/home/{username}.
     * - Logs command, user, cwd, exit code, duration (no secrets).
     *
     * @param string $username  Target Linux username
     * @param string $command   Full shell command string (already escaped)
     * @param int    $timeout   Timeout in seconds
     * @param string|null $cwd  Working directory (must be under /home/{username})
     * @return array{success: bool, output: string, error: string, exit_code: int}
     */
    public static function runAsUser(string $username, string $command, int $timeout = 120, ?string $cwd = null): array
    {
        // Validate username: only lowercase, digits, hyphen, underscore, 1-32 chars
        if (!preg_match('/^[a-z_][a-z0-9_-]{0,31}$/', $username)) {
            Log::error("ShellService::runAsUser invalid username: {$username}");
            return ['success' => false, 'output' => '', 'error' => 'Invalid username.', 'exit_code' => 1];
        }

        // Confirm user exists
        $idCheck = Process::timeout(5)->run("id -u {$username} 2>/dev/null");
        if (!$idCheck->successful()) {
            Log::error("ShellService::runAsUser user not found: {$username}");
            return ['success' => false, 'output' => '', 'error' => "User {$username} does not exist.", 'exit_code' => 1];
        }

        // Validate cwd if provided
        if ($cwd !== null) {
            $realCwd = realpath($cwd);
            $homePrefix = "/home/{$username}";
            if ($realCwd === false || !str_starts_with($realCwd, $homePrefix)) {
                Log::error("ShellService::runAsUser cwd outside home: {$cwd} for user {$username}");
                return ['success' => false, 'output' => '', 'error' => 'Working directory must be inside user home.', 'exit_code' => 1];
            }
        }

        // Build safe command via sudo
        $home = "/home/{$username}";
        $escapedCmd = escapeshellarg($command);
        $sudoCmd = "sudo -u {$username} -H HOME={$home} -- bash -c {$escapedCmd}";

        if ($cwd !== null) {
            $escapedCwd = escapeshellarg($cwd);
            $sudoCmd = "cd {$escapedCwd} && {$sudoCmd}";
        }

        $start = microtime(true);
        $result = Process::timeout($timeout)->run($sudoCmd);
        $duration = round(microtime(true) - $start, 2);

        // Log without secrets
        $logCmd = mb_substr($command, 0, 200);
        Log::info("runAsUser", [
            'user' => $username,
            'cmd' => $logCmd,
            'cwd' => $cwd,
            'exit' => $result->exitCode(),
            'duration' => $duration . 's',
        ]);

        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'error' => $result->errorOutput(),
            'exit_code' => $result->exitCode(),
        ];
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
