<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UserMonitoringService
{
    public static function getUsage(string $username): array
    {
        $cpu = ShellService::exec("ps aux | grep '^{$username}' | awk '{sum+=\$3} END {print sum}' 2>/dev/null");
        $mem = ShellService::exec("ps aux | grep '^{$username}' | awk '{sum+=\$4} END {print sum}' 2>/dev/null");
        $procs = ShellService::exec("ps aux | grep '^{$username}' | wc -l 2>/dev/null");
        return [
            'cpu' => (float) trim($cpu),
            'memory' => (float) trim($mem),
            'processes' => max(0, (int) trim($procs) - 1),
        ];
    }

    public static function getAllUsersUsage(): array
    {
        $users = DB::table('user')->pluck('username')->toArray();
        $usage = [];
        foreach ($users as $user) {
            $usage[$user] = self::getUsage($user);
        }
        return $usage;
    }

    public static function killUserProcesses(string $username): string
    {
        return ShellService::exec('pkill -u ' . escapeshellarg($username) . ' 2>&1');
    }
}
