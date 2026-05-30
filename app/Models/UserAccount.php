<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'package_id', 'domain', 'ip_address',
        'document_root', 'shell', 'shell_access',
        'disk_usage_bytes', 'disk_quota_bytes',
        'bandwidth_usage_bytes', 'bandwidth_limit_bytes',
        'dedicated_ip', 'suspended', 'suspend_reason',
    ];

    protected function casts(): array
    {
        return [
            'shell_access' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function dnsZones()
    {
        return $this->hasMany(DnsZone::class);
    }

    public function mysqlDatabases()
    {
        return $this->hasMany(MysqlDatabase::class);
    }

    public function mysqlUsers()
    {
        return $this->hasMany(MysqlUser::class);
    }

    public function emailAccounts()
    {
        return $this->hasMany(EmailAccount::class);
    }

    public function emailForwarders()
    {
        return $this->hasMany(EmailForwarder::class);
    }

    public function emailAutoresponders()
    {
        return $this->hasMany(EmailAutoresponder::class);
    }

    public function ftpAccounts()
    {
        return $this->hasMany(FtpAccount::class);
    }

    public function sslCertificates()
    {
        return $this->hasMany(SslCertificate::class);
    }

    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended === 'yes';
    }

    public function getDiskUsageFormattedAttribute(): string
    {
        return $this->formatBytes($this->disk_usage_bytes);
    }

    public function getDiskQuotaFormattedAttribute(): string
    {
        return $this->formatBytes($this->disk_quota_bytes);
    }

    public function getDiskUsagePercentAttribute(): float
    {
        if ($this->disk_quota_bytes <= 0) return 0;
        return round(($this->disk_usage_bytes / $this->disk_quota_bytes) * 100, 1);
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
}
