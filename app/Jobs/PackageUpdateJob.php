<?php

namespace App\Jobs;

use App\Services\ShellService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PackageUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        public string $type = 'security',
    ) {}

    public function handle(): void
    {
        Log::info("PackageUpdateJob: Starting (type: {$this->type})");

        try {
            $cmd = match ($this->type) {
                'all' => 'dnf -y update 2>&1',
                'security' => 'dnf -y update --security 2>&1',
                default => 'dnf -y update --security 2>&1',
            };

            $output = ShellService::exec($cmd, 3000);
            Log::info("PackageUpdateJob: Completed.\n{$output}");
            ShellService::exec("echo '[package-update] type={$this->type}' >> " . escapeshellarg(config('openpanel.security.log_file', '/var/log/openpanel/audit.log')));
        } catch (\Throwable $e) {
            Log::error("PackageUpdateJob: Failed: {$e->getMessage()}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("PackageUpdateJob permanently failed: {$exception->getMessage()}");
    }
}
