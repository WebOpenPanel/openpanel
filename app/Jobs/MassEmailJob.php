<?php

namespace App\Jobs;

use App\Models\LinuxAuthUser;
use App\Services\ShellService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MassEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public string $subject,
        public string $message,
        public string $recipients = 'all',
    ) {}

    public function handle(): void
    {
        try {
            $users = match ($this->recipients) {
                'resellers' => LinuxAuthUser::resellers(),
                'users' => LinuxAuthUser::clients(),
                default => LinuxAuthUser::all(),
            };

            $hostname = ShellService::exec("hostname -f");
            if (empty($hostname)) {
                $hostname = ShellService::exec("hostname");
            }

            $sent = 0;
            $failed = 0;

            foreach ($users as $user) {
                $email = escapeshellarg($user->username . '@' . $hostname);
                $subject = escapeshellarg($this->subject);
                $body = escapeshellarg($this->message);

                $result = ShellService::run("echo {$body} | mail -s {$subject} {$email} 2>&1");
                if ($result['success']) {
                    $sent++;
                } else {
                    $failed++;
                    Log::warning("MassEmailJob: Failed to send to {$user->username}: {$result['error']}");
                }
            }

            Log::info("MassEmailJob: Sent to {$sent} users, {$failed} failed (group: {$this->recipients})");
            ShellService::exec("echo '[mass-email] group={$this->recipients} sent={$sent} failed={$failed}' >> " . escapeshellarg(config('openpanel.security.log_file', '/var/log/openpanel/audit.log')));
        } catch (\Throwable $e) {
            Log::error("MassEmailJob: Failed: {$e->getMessage()}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("MassEmailJob permanently failed: {$exception->getMessage()}");
    }
}
