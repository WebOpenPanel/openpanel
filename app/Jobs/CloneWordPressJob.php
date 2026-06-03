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

class CloneWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1200;

    public function __construct(
        public int $siteId,
        public string $targetDomain,
        public string $targetPath = '',
    ) {}

    public function handle(WordPressService $wp): void
    {
        $site = WordPressSite::find($this->siteId);
        if (!$site) {
            Log::error("CloneWordPressJob: Site {$this->siteId} not found");
            return;
        }

        Log::info("CloneWordPressJob: Cloning {$site->domain} to {$this->targetDomain}");
        $result = $wp->cloneSite($site, $this->targetDomain, $this->targetPath);

        if ($result['success']) {
            Log::info("CloneWordPressJob: Clone completed for {$site->domain} → {$this->targetDomain}");
        } else {
            Log::error("CloneWordPressJob: Clone failed: " . ($result['message'] ?? ''));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("CloneWordPressJob: Exception for site {$this->siteId}: {$exception->getMessage()}");
    }
}
