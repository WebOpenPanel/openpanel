<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\BackupService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('openpanel:backups:run {--force}', function () {
    $result = BackupService::runScheduledBackups((bool) $this->option('force'));
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return ($result['success'] ?? false) ? 0 : 1;
})->purpose('Run due OpenPanel scheduled backups');

Schedule::command('openpanel:backups:run')->hourly()->withoutOverlapping();
