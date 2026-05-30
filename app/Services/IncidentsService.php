<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class IncidentsService
{
    protected static string $incidentsDir = '/usr/local/openpanel/.conf/incidents';
    protected static string $incidentsFile = '/usr/local/openpanel/.conf/incidents.json';

    public static function getIncidents(int $limit = 100): array
    {
        if (!file_exists(self::$incidentsFile)) {
            return [];
        }
        $incidents = json_decode(file_get_contents(self::$incidentsFile), true) ?: [];
        usort($incidents, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));
        return array_slice($incidents, 0, $limit);
    }

    public static function addIncident(array $data): void
    {
        $incidents = file_exists(self::$incidentsFile) ? (json_decode(file_get_contents(self::$incidentsFile), true) ?: []) : [];

        $incident = [
            'id' => uniqid('inc_'),
            'type' => $data['type'] ?? 'unknown',
            'severity' => $data['severity'] ?? 'info',
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'ip' => $data['ip'] ?? '',
            'user' => $data['user'] ?? '',
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'resolved' => false,
        ];

        $incidents[] = $incident;

        if (count($incidents) > 1000) {
            $incidents = array_slice($incidents, -1000);
        }

        File::ensureDirectoryExists(dirname(self::$incidentsFile));
        file_put_contents(self::$incidentsFile, json_encode($incidents, JSON_PRETTY_PRINT));
    }

    public static function resolveIncident(string $id): array
    {
        $incidents = file_exists(self::$incidentsFile) ? (json_decode(file_get_contents(self::$incidentsFile), true) ?: []) : [];
        foreach ($incidents as &$inc) {
            if (($inc['id'] ?? '') === $id) {
                $inc['resolved'] = true;
                $inc['resolved_at'] = date('Y-m-d H:i:s');
                break;
            }
        }
        file_put_contents(self::$incidentsFile, json_encode($incidents, JSON_PRETTY_PRINT));
        return ['success' => true, 'message' => 'Incident resolved.'];
    }

    public static function deleteIncident(string $id): array
    {
        $incidents = file_exists(self::$incidentsFile) ? (json_decode(file_get_contents(self::$incidentsFile), true) ?: []) : [];
        $incidents = array_filter($incidents, fn($inc) => ($inc['id'] ?? '') !== $id);
        file_put_contents(self::$incidentsFile, json_encode(array_values($incidents), JSON_PRETTY_PRINT));
        return ['success' => true, 'message' => 'Incident deleted.'];
    }

    public static function clearAll(): array
    {
        file_put_contents(self::$incidentsFile, '[]');
        return ['success' => true, 'message' => 'All incidents cleared.'];
    }

    public static function getStats(): array
    {
        $incidents = self::getIncidents(10000);
        $stats = ['total' => count($incidents), 'unresolved' => 0, 'by_severity' => [], 'by_type' => []];

        foreach ($incidents as $inc) {
            if (!($inc['resolved'] ?? false)) {
                $stats['unresolved']++;
            }
            $sev = $inc['severity'] ?? 'info';
            $stats['by_severity'][$sev] = ($stats['by_severity'][$sev] ?? 0) + 1;
            $type = $inc['type'] ?? 'unknown';
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        }

        return $stats;
    }

    public static function scanForIncidents(): array
    {
        $found = [];

        $failedLogins = ShellService::exec("grep 'Failed password' /var/log/secure 2>/dev/null | tail -20");
        if ($failedLogins) {
            $lines = explode("\n", trim($failedLogins));
            $ipCounts = [];
            foreach ($lines as $line) {
                if (preg_match('/from (\S+)/', $line, $m)) {
                    $ipCounts[$m[1]] = ($ipCounts[$m[1]] ?? 0) + 1;
                }
            }
            foreach ($ipCounts as $ip => $count) {
                if ($count >= 5) {
                    self::addIncident([
                        'type' => 'brute_force',
                        'severity' => 'high',
                        'title' => "Brute force detected from {$ip}",
                        'description' => "{$count} failed login attempts from {$ip}",
                        'ip' => $ip,
                    ]);
                    $found[] = "Brute force: {$ip} ({$count} attempts)";
                }
            }
        }

        $diskUsage = (int)ShellService::exec("df / | tail -1 | awk '{print $5}' | tr -d '%'");
        if ($diskUsage > 90) {
            self::addIncident([
                'type' => 'disk_usage',
                'severity' => $diskUsage > 95 ? 'critical' : 'high',
                'title' => "Disk usage at {$diskUsage}%",
                'description' => "Root partition is {$diskUsage}% full",
            ]);
            $found[] = "Disk usage: {$diskUsage}%";
        }

        $load = sys_getloadavg();
        $cpuCount = (int)ShellService::exec('nproc');
        if ($load && $load[0] > $cpuCount * 2) {
            self::addIncident([
                'type' => 'high_load',
                'severity' => 'warning',
                'title' => "High server load: {$load[0]}",
                'description' => "Load average {$load[0]} exceeds 2x CPU count ({$cpuCount})",
            ]);
            $found[] = "High load: {$load[0]}";
        }

        return ['success' => true, 'found' => $found, 'message' => count($found) . ' new incidents found.'];
    }
}
