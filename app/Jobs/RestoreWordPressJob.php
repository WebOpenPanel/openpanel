<?php

namespace App\Jobs;

use App\Models\WordPressSite;
use App\Models\WordPressBackup;
use App\Services\WordPressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RestoreWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public int $siteId,
        public int $backupId,
    ) {}

    public function handle(WordPressService $wp): void
    {
        $site = WordPressSite::find($this->siteId);
        $backup = WordPressBackup::find($this->backupId);

        if (!$site || !$backup) {
            Log::error("RestoreWordPressJob: Site or backup not found (site={$this->siteId}, backup={$this->backupId})");
            return;
        }

        Log::info("RestoreWordPressJob: Restoring {$site->domain} from backup {$backup->id}");
        $result = $wp->restoreSite($site, $backup);

        if ($result['success']) {
            Log::info("RestoreWordPressJob: Restore completed for {$site->domain}");
        } else {
            Log::error("RestoreWordPressJob: Restore failed for {$site->domain}: " . ($result['message'] ?? ''));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RestoreWordPressJob: Exception for site {$this->siteId}: {$exception->getMessage()}");
    }
}
