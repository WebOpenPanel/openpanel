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

class UpdateWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public int $siteId,
        public string $type = 'core',
        public ?string $target = null,
    ) {}

    public function handle(WordPressService $wp): void
    {
        $site = WordPressSite::find($this->siteId);
        if (!$site) {
            Log::error("UpdateWordPressJob: Site {$this->siteId} not found");
            return;
        }

        Log::info("UpdateWordPressJob: {$this->type} update for {$site->domain}");

        $result = match ($this->type) {
            'core' => $wp->updateCore($site),
            'plugins' => $wp->updatePlugins($site, $this->target),
            'themes' => $wp->updateThemes($site, $this->target),
            default => ['success' => false, 'message' => "Unknown update type: {$this->type}"],
        };

        if ($result['success']) {
            Log::info("UpdateWordPressJob: {$this->type} update completed for {$site->domain}");
        } else {
            Log::error("UpdateWordPressJob: {$this->type} update failed for {$site->domain}: " . ($result['message'] ?? ''));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("UpdateWordPressJob: Exception for site {$this->siteId}: {$exception->getMessage()}");
    }
}
