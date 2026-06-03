<?php

namespace App\Jobs;

use App\Services\WordPressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InstallWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public array $params,
    ) {}

    public function handle(WordPressService $wp): void
    {
        Log::info("InstallWordPressJob: Starting for {$this->params['domain']}");
        $result = $wp->installWordPress($this->params);

        if ($result['success']) {
            Log::info("InstallWordPressJob: Completed for {$this->params['domain']}");
        } else {
            Log::error("InstallWordPressJob: Failed for {$this->params['domain']}: {$result['message']}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("InstallWordPressJob: Exception for {$this->params['domain']}: {$exception->getMessage()}");
    }
}
