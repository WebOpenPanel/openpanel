<?php

namespace App\Services;

class CronService
{
    const CRON_DIR = '/var/spool/cron/';

    public static function getCrontab(string $username = 'root'): string
    {
        $cronFile = self::CRON_DIR . $username;
        if (file_exists($cronFile)) {
            return file_get_contents($cronFile) ?: '';
        }
        return ShellService::exec('crontab -l -u ' . escapeshellarg($username) . ' 2>/dev/null');
    }

    public static function saveCrontab(string $username, string $content): bool
    {
        $cronFile = self::CRON_DIR . $username;
        file_put_contents($cronFile, $content, LOCK_EX);
        ShellService::exec('crontab -u ' . escapeshellarg($username) . ' ' . escapeshellarg($cronFile) . ' 2>&1');
        return true;
    }

    public static function addJob(string $username, string $schedule, string $command): bool
    {
        $cronFile = self::CRON_DIR . $username;
        $newJob = $schedule . ' ' . $command;
        file_put_contents($cronFile, $newJob . "\n", FILE_APPEND | LOCK_EX);
        ShellService::exec('crontab -u ' . escapeshellarg($username) . ' ' . escapeshellarg($cronFile) . ' 2>&1');
        return true;
    }

    public static function removeJob(string $username, int $lineNumber): bool
    {
        $cronFile = self::CRON_DIR . $username;
        if (!file_exists($cronFile)) return false;

        $line = $lineNumber + 1;
        ShellService::exec("sed -i '{$line}d' " . escapeshellarg($cronFile));
        ShellService::exec('crontab -u ' . escapeshellarg($username) . ' ' . escapeshellarg($cronFile) . ' 2>&1');
        return true;
    }

    public static function editJob(string $username, int $lineNumber, string $schedule, string $command): bool
    {
        $cronFile = self::CRON_DIR . $username;
        if (!file_exists($cronFile)) return false;

        $content = file_get_contents($cronFile);
        $lines = explode("\n", rtrim($content, "\n"));
        if ($lineNumber < 0 || $lineNumber >= count($lines)) return false;

        $lines[$lineNumber] = $schedule . ' ' . $command;
        file_put_contents($cronFile, implode("\n", $lines) . "\n", LOCK_EX);
        ShellService::exec('crontab -u ' . escapeshellarg($username) . ' ' . escapeshellarg($cronFile) . ' 2>&1');
        return true;
    }

    public static function toggleJob(string $username, int $lineNumber): bool
    {
        $cronFile = self::CRON_DIR . $username;
        if (!file_exists($cronFile)) return false;

        $content = file_get_contents($cronFile);
        $lines = explode("\n", $content);

        if (!isset($lines[$lineNumber])) return false;

        $line = $lines[$lineNumber];
        $trimmedLine = preg_replace("/^\s+|\s+$/", '', $line);

        if (empty($trimmedLine)) return false;

        if (preg_match('/^##/', $trimmedLine)) {
            $lines[$lineNumber] = str_replace('##', '', $line);
        } else {
            $lines[$lineNumber] = '##' . $line;
        }

        file_put_contents($cronFile, implode("\n", $lines), LOCK_EX);
        ShellService::exec('crontab -u ' . escapeshellarg($username) . ' ' . escapeshellarg($cronFile) . ' 2>&1');
        return true;
    }

    public static function runCronLine(string $username, int $lineNumber): string
    {
        $cronFile = self::CRON_DIR . $username;
        if (!file_exists($cronFile)) return 'Cron file not found';

        $content = file_get_contents($cronFile);
        $lines = explode("\n", $content);

        if (!isset($lines[$lineNumber])) return 'Line not found';

        $line = preg_replace("/^\s+|\s+$/", '', $lines[$lineNumber]);
        if (empty($line) || preg_match('/^##/', $line) || preg_match('/^#/', $line)) {
            return 'Cannot run disabled or comment line';
        }

        $parts = self::analyzeCronLine($line);
        if (empty($parts['command'])) return 'Invalid cron line';

        $command = $parts['command'];
        if (!preg_match('/^SHELL=/', $command) && !preg_match('/^PATH=/', $command) && !preg_match('/^CONTENT_TYPE=/', $command)) {
            return ShellService::exec($command . ' 2>&1');
        }
        return 'Skipped environment variable line';
    }

    public static function parseJobs(string $username): array
    {
        $content = self::getCrontab($username);
        if (empty(trim($content))) return [];

        $jobs = [];
        $lineNum = 0;
        foreach (explode("\n", $content) as $line) {
            $line = rtrim($line, "\r");
            $trimmedLine = preg_replace("/^\s+|\s+$/", '', $line);

            if (empty($trimmedLine)) {
                $lineNum++;
                continue;
            }

            $disabled = false;
            $displayLine = $trimmedLine;

            if (preg_match('/^##/', $trimmedLine)) {
                $disabled = true;
                $displayLine = str_replace('##', '', $trimmedLine);
            }

            if (preg_match('/^SHELL=/', $displayLine) || preg_match('/^PATH=/', $displayLine) || preg_match('/^CONTENT_TYPE=/', $displayLine)) {
                $jobs[] = [
                    'type' => 'env',
                    'content' => $displayLine,
                    'raw' => $line,
                    'line' => $lineNum,
                    'enabled' => !$disabled,
                ];
                $lineNum++;
                continue;
            }

            $parsed = self::analyzeCronLine($displayLine);

            if (empty($parsed['command'])) {
                $jobs[] = [
                    'type' => 'comment',
                    'content' => $trimmedLine,
                    'raw' => $line,
                    'line' => $lineNum,
                    'enabled' => true,
                ];
            } else {
                $jobs[] = [
                    'type' => 'job',
                    'line' => $lineNum,
                    'minute' => $parsed['minute'],
                    'hour' => $parsed['hour'],
                    'day' => $parsed['day'],
                    'month' => $parsed['month'],
                    'weekday' => $parsed['weekday'],
                    'command' => $parsed['command'],
                    'raw' => $line,
                    'enabled' => !$disabled,
                ];
            }
            $lineNum++;
        }
        return $jobs;
    }

    protected static function analyzeCronLine(string $line): array
    {
        $line = preg_replace("/^\s+|\s+$/", '', $line);

        if (empty($line) || preg_match('/^#/', $line)) {
            return ['command' => ''];
        }

        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 6) {
            return ['command' => ''];
        }

        return [
            'minute' => $parts[0],
            'hour' => $parts[1],
            'day' => $parts[2],
            'month' => $parts[3],
            'weekday' => $parts[4],
            'command' => implode(' ', array_slice($parts, 5)),
        ];
    }

    public static function getAllCrontabs(): array
    {
        $crontabs = [];
        if (!is_dir(self::CRON_DIR)) return $crontabs;
        foreach (ShellService::dirList(self::CRON_DIR) as $file) {
            $fullPath = self::CRON_DIR . $file;
            if (is_file($fullPath)) {
                $crontabs[] = [
                    'username' => $file,
                    'content' => file_get_contents($fullPath),
                    'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                ];
            }
        }
        return $crontabs;
    }

    public static function getCronLog(int $lines = 50): string
    {
        $logFiles = ['/var/log/cron', '/var/log/syslog'];
        foreach ($logFiles as $logFile) {
            if (file_exists($logFile)) {
                return ShellService::exec("grep CRON " . escapeshellarg($logFile) . " 2>/dev/null | tail -n {$lines}");
            }
        }
        return 'Cron log not found';
    }

    public static function getCommonSchedules(): array
    {
        return [
            ['label' => 'Every minute', 'value' => '* * * * *'],
            ['label' => 'Every 5 minutes', 'value' => '*/5 * * * *'],
            ['label' => 'Every 15 minutes', 'value' => '*/15 * * * *'],
            ['label' => 'Every 30 minutes', 'value' => '*/30 * * * *'],
            ['label' => 'Every hour', 'value' => '0 * * * *'],
            ['label' => 'Every 2 hours', 'value' => '0 */2 * * *'],
            ['label' => 'Every 6 hours', 'value' => '0 */6 * * *'],
            ['label' => 'Every 12 hours', 'value' => '0 */12 * * *'],
            ['label' => 'Daily at midnight', 'value' => '0 0 * * *'],
            ['label' => 'Daily at 2 AM', 'value' => '0 2 * * *'],
            ['label' => 'Weekly (Sunday midnight)', 'value' => '0 0 * * 0'],
            ['label' => 'Monthly (1st midnight)', 'value' => '0 0 1 * *'],
            ['label' => 'Yearly (Jan 1st)', 'value' => '0 0 1 1 *'],
        ];
    }

    public static function cronDaemonStatus(): array
    {
        $output = ShellService::exec('systemctl is-active crond 2>/dev/null');
        return ['active' => trim($output) === 'active'];
    }

    public static function cronDaemonAction(string $action): string
    {
        return ServerService::serviceAction($action, 'crond');
    }
}
