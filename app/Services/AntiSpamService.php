<?php

namespace App\Services;

class AntiSpamService
{
    const SPAMHAUS_CRON = '/etc/cron.daily/spamhaus';

    public static function isSpamhausInstalled(): bool
    {
        return file_exists(self::SPAMHAUS_CRON);
    }

    public static function installSpamhaus(): bool
    {
        $script = 'curl -s http://www.spamhaus.org/drop/drop.lasso |grep ^[1-9]|cut -f 1 -d \' \' | xargs -iX -n 1 csf -td X 86400 -d in \'spamhaus\'';
        ShellService::writeFile(self::SPAMHAUS_CRON, $script);
        ShellService::exec('chmod 744 ' . self::SPAMHAUS_CRON);
        return file_exists(self::SPAMHAUS_CRON);
    }

    public static function uninstallSpamhaus(): bool
    {
        if (file_exists(self::SPAMHAUS_CRON)) @unlink(self::SPAMHAUS_CRON);
        return !file_exists(self::SPAMHAUS_CRON);
    }

    public static function listBlockedIps(): string
    {
        return ShellService::exec('curl -s http://www.spamhaus.org/drop/drop.lasso 2>&1');
    }
}
