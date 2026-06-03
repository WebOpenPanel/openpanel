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

class PurgeWordPressCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $siteId,
    ) {}

    public function handle(WordPressService $wp): void
    {
        $site = WordPressSite::find($this->siteId);
        if (!$site) {
            Log::error("PurgeWordPressCacheJob: Site {$this->siteId} not found");
            return;
        }

        Log::info("PurgeWordPressCacheJob: Purging cache for {$site->domain}");
        $result = $wp->purgeCache($site);

        if ($result['success']) {
            Log::info("PurgeWordPressCacheJob: Cache purged for {$site->domain}");
        } else {
            Log::error("PurgeWordPressCacheJob: Failed for {$site->domain}: " . ($result['message'] ?? ''));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("PurgeWordPressCacheJob: Exception for site {$this->siteId}: {$exception->getMessage()}");
    }
}
