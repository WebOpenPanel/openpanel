<?php

namespace App\Services;

class ServiceMonitorService
{
    const MONITOR_CONF = '/usr/local/etc/services-monitor-systemd';
    const MONITOR_SCRIPT = '/usr/local/bin/svcMonitor-systemd';
    const MONITOR_CRON = '/etc/cron.d/svcMonitor-systemd';

    public static function listServices(): array
    {
        $output = ShellService::exec('systemctl list-unit-files | grep ".service" | grep "enabled\\|disabled" | grep -v "^systemd" | awk \'{print $1}\'');
        $services = [];
        $monitored = self::getMonitored();
        foreach (array_filter(explode("\n", $output)) as $svc) {
            $name = trim(str_replace('.service', '', $svc));
            $services[] = ['name' => $name, 'monitored' => in_array($name, $monitored)];
        }
        return $services;
    }

    public static function getMonitored(): array
    {
        if (!file_exists(self::MONITOR_CONF)) return [];
        return array_filter(explode("\n", ShellService::readFile(self::MONITOR_CONF)));
    }

    public static function saveMonitored(array $services): bool
    {
        ShellService::writeFile(self::MONITOR_CONF, implode("\n", $services));
        return true;
    }

    public static function isEnabled(): bool
    {
        return file_exists(self::MONITOR_CRON);
    }

    public static function enable(string $email, int $frequency = 5): bool
    {
        if (!file_exists(self::MONITOR_SCRIPT)) {
            $script = "#!/bin/bash\nexport PATH=/sbin:/usr/sbin:/bin:/usr/bin:/usr/local/sbin:/usr/local/bin\nfor i in `cat /usr/local/etc/services-monitor-systemd`\ndo\nSERVICESTATUS=`systemctl is-active \$i`\n  if [[ (\"\$SERVICESTATUS\" != \"active\") ]]\n  then\n    echo Restarted service \$i on `hostname` at `date`\n    systemctl stop \$i\n    sleep 1\n    systemctl start \$i\n  fi\ndone\n";
            ShellService::writeFile(self::MONITOR_SCRIPT, $script);
            ShellService::exec('chmod 744 ' . self::MONITOR_SCRIPT);
        }
        $cronContent = "MAILTO={$email}\n*/{$frequency} * * * * root " . self::MONITOR_SCRIPT . "\n";
        ShellService::writeFile(self::MONITOR_CRON, $cronContent);
        return true;
    }

    public static function disable(): bool
    {
        if (file_exists(self::MONITOR_CRON)) @unlink(self::MONITOR_CRON);
        return true;
    }
}
