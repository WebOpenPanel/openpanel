<?php

namespace App\Services;

class EmailStatsService
{
    const PFLOGSUMM_BIN = '/usr/sbin/pflogsumm';
    const POSTFIX_LOG = '/var/log/maillog';

    public static function isInstalled(): bool
    {
        return file_exists(self::PFLOGSUMM_BIN);
    }

    public static function install(): string
    {
        return ShellService::exec('yum -y install postfix-perl-scripts 2>&1');
    }

    public static function getDailyStats(): string
    {
        if (!file_exists(self::PFLOGSUMM_BIN)) return 'pflogsumm not installed';
        return ShellService::exec(self::PFLOGSUMM_BIN . ' --detailed=1 --problems_first ' . self::POSTFIX_LOG . ' 2>/dev/null');
    }

    public static function getStatsForDate(string $date): string
    {
        if (!file_exists(self::PFLOGSUMM_BIN)) return 'pflogsumm not installed';
        $logFile = self::POSTFIX_LOG . '-' . $date;
        if (!file_exists($logFile)) {
            $logFile = self::POSTFIX_LOG;
        }
        return ShellService::exec(self::PFLOGSUMM_BIN . ' --detailed=1 --problems_first ' . escapeshellarg($logFile) . ' 2>/dev/null');
    }

    public static function getWeeklyStats(): string
    {
        if (!file_exists(self::PFLOGSUMM_BIN)) return 'pflogsumm not installed';
        $logs = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $logFile = self::POSTFIX_LOG . '-' . $date;
            if (file_exists($logFile)) $logs[] = $logFile;
        }
        if (empty($logs)) $logs[] = self::POSTFIX_LOG;
        $logArg = implode(' ', array_map('escapeshellarg', $logs));
        return ShellService::exec("cat {$logArg} | " . self::PFLOGSUMM_BIN . ' --detailed=1 2>/dev/null');
    }

    public static function getQueueCount(): int
    {
        return (int) trim(ShellService::exec('postqueue -p 2>/dev/null | tail -1 | awk \'{print $5}\''));
    }

    public static function flushQueue(): string
    {
        return ShellService::exec('postfix flush 2>&1');
    }

    public static function deleteQueue(): string
    {
        return ShellService::exec('postsuper -d ALL 2>&1');
    }

    public static function getPostfixConfig(): string
    {
        return ShellService::readFile('/etc/postfix/main.cf');
    }

    public static function savePostfixConfig(string $content): bool
    {
        ShellService::writeFile('/etc/postfix/main.cf', $content);
        ServerService::serviceAction('restart', 'postfix');
        return true;
    }
}
