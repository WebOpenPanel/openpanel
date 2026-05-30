<?php

namespace App\Http\Controllers;

use App\Services\EmailStatsService;

class EmailStatsController extends Controller
{
    public function index()
    {
        $installed = EmailStatsService::isInstalled();
        $stats = $installed ? EmailStatsService::getDailyStats() : '';
        $queueCount = $installed ? EmailStatsService::getQueueCount() : 0;
        return view('email_stats.index', compact('installed', 'stats', 'queueCount'));
    }

    public function daily()
    {
        $stats = EmailStatsService::getDailyStats();
        return view('email_stats.daily', compact('stats'));
    }

    public function weekly()
    {
        $stats = EmailStatsService::getWeeklyStats();
        return view('email_stats.weekly', compact('stats'));
    }

    public function flushQueue()
    {
        EmailStatsService::flushQueue();
        return back()->with('success', 'Mail queue flushed.');
    }

    public function deleteQueue()
    {
        EmailStatsService::deleteQueue();
        return back()->with('success', 'Mail queue deleted.');
    }
}
