<?php

namespace App\Jobs;

use App\Models\WordPressSite;
use App\Services\WordPressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BackupWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public int $siteId,
        public string $type = 'full',
    ) {}

    public function handle(WordPressService $wp): void
    {
        $site = WordPressSite::find($this->siteId);
        if (!$site) {
            Log::error("BackupWordPressJob: Site {$this->siteId} not found");
            return;
        }

        Log::info("BackupWordPressJob: {$this->type} backup for {$site->domain}");
        $result = $wp->backupSite($site, $this->type);

        if ($result['success']) {
            Log::info("BackupWordPressJob: Backup completed for {$site->domain}");
        } else {
            Log::error("BackupWordPressJob: Backup failed for {$site->domain}: " . ($result['message'] ?? ''));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("BackupWordPressJob: Exception for site {$this->siteId}: {$exception->getMessage()}");
    }
}
