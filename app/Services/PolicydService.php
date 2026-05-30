<?php

namespace App\Services;

class PolicydService
{
    protected static string $policydConfig = '/etc/policyd/cluebringer.conf';
    protected static string $policydDb = '/var/lib/cluebringer/cluebringer.db';

    public static function isInstalled(): bool
    {
        return file_exists(self::$policydConfig) || file_exists(self::$policydDb);
    }

    public static function install(): array
    {
        $output = ShellService::exec('dnf -y install cluebringer 2>&1 || yum -y install cluebringer 2>&1');
        if (self::isInstalled()) {
            ShellService::exec('systemctl enable postfix-cluebringer && systemctl start postfix-cluebringer');
            return ['success' => true, 'message' => 'Policyd installed.', 'output' => $output];
        }
        return ['success' => false, 'message' => 'Installation failed.', 'output' => $output];
    }

    public static function getPolicies(): array
    {
        if (!self::isInstalled()) {
            return [];
        }

        $output = ShellService::exec("sqlite3 " . self::$policydDb . " \"SELECT ID, Name, Priority, Disabled FROM policies ORDER BY Priority\" 2>/dev/null");
        $policies = [];
        foreach (explode("\n", trim($output)) as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $policies[] = [
                    'id' => $parts[0],
                    'name' => $parts[1],
                    'priority' => $parts[2],
                    'disabled' => (int)$parts[3],
                ];
            }
        }
        return $policies;
    }

    public static function getRateLimits(): array
    {
        if (!self::isInstalled()) {
            return [];
        }

        $output = ShellService::exec("sqlite3 " . self::$policydDb . " \"SELECT _rowid_, PolicyID, Track, Period, MsgsCount, DataSize FROM quotas LIMIT 50\" 2>/dev/null");
        $limits = [];
        foreach (explode("\n", trim($output)) as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 6) {
                $limits[] = [
                    'id' => $parts[0],
                    'policy_id' => $parts[1],
                    'track' => $parts[2],
                    'period' => $parts[3],
                    'max_messages' => $parts[4],
                    'max_size' => $parts[5],
                ];
            }
        }
        return $limits;
    }

    public static function addRateLimit(array $data): array
    {
        $policyId = $data['policy_id'] ?? 1;
        $track = $data['track'] ?? 'SenderIP';
        $period = $data['period'] ?? 60;
        $msgs = $data['max_messages'] ?? 100;
        $size = $data['max_size'] ?? 0;

        $sql = "INSERT INTO quotas (PolicyID, Track, Period, MsgsCount, DataSize) VALUES ({$policyId}, '{$track}', {$period}, {$msgs}, {$size})";
        ShellService::exec("sqlite3 " . self::$policydDb . " \"{$sql}\" 2>&1");

        return ['success' => true, 'message' => 'Rate limit added.'];
    }

    public static function removeRateLimit(int $id): array
    {
        ShellService::exec("sqlite3 " . self::$policydDb . " \"DELETE FROM quotas WHERE _rowid_ = {$id}\" 2>&1");
        return ['success' => true, 'message' => 'Rate limit removed.'];
    }

    public static function togglePolicy(int $id, int $disabled): array
    {
        ShellService::exec("sqlite3 " . self::$policydDb . " \"UPDATE policies SET Disabled = {$disabled} WHERE ID = {$id}\" 2>&1");
        return ['success' => true, 'message' => $disabled ? 'Policy disabled.' : 'Policy enabled.'];
    }

    public static function restart(): array
    {
        $output = ShellService::exec('systemctl restart postfix-cluebringer 2>&1');
        return ['success' => true, 'output' => $output];
    }

    public static function getStatus(): array
    {
        $running = trim(ShellService::exec('systemctl is-active postfix-cluebringer 2>/dev/null')) === 'active';
        return ['running' => $running, 'installed' => self::isInstalled()];
    }
}
