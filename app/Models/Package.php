<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'disk_space_mb', 'bandwidth_mb',
        'max_domains', 'max_subdomains', 'max_email_accounts',
        'max_databases', 'max_ftp_accounts', 'max_parked_domains',
        'max_addon_domains', 'max_email_lists', 'max_cron_jobs',
        'shell_access', 'dedicated_ip', 'php_version', 'web_server',
        'ssl_enabled', 'hourly_emails', 'reseller', 'max_accounts',
        'cgroups', 'nproc', 'apache_nproc', 'inode', 'nofile',
        'nodejs_apps', 'mongo_database', 'pgresql_database',
        'tomcat_apps', 'cron_data',
    ];

    protected function casts(): array
    {
        return [
            'shell_access' => 'boolean',
            'dedicated_ip' => 'boolean',
            'ssl_enabled' => 'boolean',
        ];
    }

    public function userAccounts()
    {
        return $this->hasMany(UserAccount::class);
    }

    public function getDiskSpaceFormattedAttribute(): string
    {
        return $this->formatBytes($this->disk_space_mb * 1024 * 1024);
    }

    public function getBandwidthFormattedAttribute(): string
    {
        return $this->formatBytes($this->bandwidth_mb * 1024 * 1024);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function isReseller(): bool
    {
        return $this->reseller === '1' || $this->reseller === 'reseller';
    }

    public function isUnlimited(string $field): bool
    {
        return ($this->{$field} ?? 0) < 0;
    }

    public function displayValue(string $field): string|int
    {
        $value = $this->{$field} ?? 0;
        return $value < 0 ? '∞' : $value;
    }

    public function propagateToUsers(): void
    {
        $this->userAccounts()->update([
            'package_name' => $this->name,
        ]);
    }
}
