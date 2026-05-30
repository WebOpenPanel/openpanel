<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Facades\DB;

class PackageService
{
    public static function updatePackage(int $packageId): void
    {
        $package = Package::find($packageId);
        if (!$package) return;
        $package->propagateToUsers();
        self::policydUpdate(null, $packageId, null, null);
    }

    public static function deletePackage(int $packageId): array
    {
        $userCount = DB::table('user_accounts')->where('package_id', $packageId)->count();
        if ($userCount > 0) {
            return ['success' => false, 'message' => 'Package is assigned to ' . $userCount . ' account(s).'];
        }
        Package::findOrFail($packageId)->delete();
        self::policydDeletePackage($packageId);
        return ['success' => true];
    }

    public static function policydUpdate(?int $limit = null, ?int $packageId = null, ?string $user = null, ?string $domain = null): void
    {
        $exists = ShellService::exec("which policyd-sender 2>/dev/null");
        if (empty(trim($exists))) return;

        if ($packageId) {
            $package = Package::find($packageId);
            if (!$package) return;
            $hourlyLimit = $package->hourly_emails ?: 50;
            ShellService::exec("policyd-sender --quota \"Name=PackageQuota{$packageId};Track=SenderDomain;" .
                "Period=3600;Max={$hourlyLimit}\" 2>/dev/null || true");
        }

        if ($user) {
            $account = DB::table('user_accounts')->where('username', $user)->first();
            if ($account) {
                $package = Package::find($account->package_id);
                $hourlyLimit = $package ? ($package->hourly_emails ?: 50) : 50;
                ShellService::exec("policyd-sender --quota \"Name=UserQuota{$user};Track=Sender;" .
                    "Period=3600;Max={$hourlyLimit}\" 2>/dev/null || true");
            }
        }
    }

    public static function policydDeletePackage(int $packageId): void
    {
        $exists = ShellService::exec("which policyd-sender 2>/dev/null");
        if (empty(trim($exists))) return;
        ShellService::exec("policyd-sender --delete-quota \"Name=PackageQuota{$packageId}\" 2>/dev/null || true");
    }

    public static function getMongoDbInstalled(): bool
    {
        return file_exists('/usr/lib/systemd/system/mongod.service');
    }

    public static function getPgsqlInstalled(): bool
    {
        return (int) trim(ShellService::exec("ls /usr/lib/systemd/system/postgresql*.service 2>/dev/null | wc -l")) > 0;
    }

    public static function getTomcatInstalled(): bool
    {
        return (int) trim(ShellService::exec("ls /usr/local/tomcat* 2>/dev/null | wc -l")) > 0;
    }

    public static function getResellerOptions(): array
    {
        $resellers = DB::table('user_accounts')->where('reseller', '1')->pluck('username')->toArray();
        $options = ['' => 'General', '1' => 'Reseller'];
        foreach ($resellers as $r) {
            $options[$r] = ' - ' . $r;
        }
        return $options;
    }
}
