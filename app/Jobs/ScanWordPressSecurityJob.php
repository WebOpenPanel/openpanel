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

class ScanWordPressSecurityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public int $siteId,
    ) {}

    public function handle(WordPressService $wp): void
    {
        $site = WordPressSite::find($this->siteId);
        if (!$site) {
            Log::error("ScanWordPressSecurityJob: Site {$this->siteId} not found");
            return;
        }

        Log::info("ScanWordPressSecurityJob: Scanning {$site->domain}");
        $result = $wp->scanSite($site);

        if ($result['success']) {
            Log::info("ScanWordPressSecurityJob: Scan completed for {$site->domain}");
        } else {
            Log::error("ScanWordPressSecurityJob: Scan failed for {$site->domain}: " . ($result['message'] ?? ''));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ScanWordPressSecurityJob: Exception for site {$this->siteId}: {$exception->getMessage()}");
    }
}
