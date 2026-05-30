<?php

namespace App\Http\Controllers;

use App\Models\CronJob;
use App\Services\CronService;
use Illuminate\Http\Request;

class CronController extends Controller
{
    public function index()
    {
        $cronJobs = CronJob::with('user')->latest()->paginate(20);
        $schedules = CronService::getCommonSchedules();
        return view('cron.index', compact('cronJobs', 'schedules'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'command' => 'required|string',
            'minute' => 'required|string|max:20',
            'hour' => 'required|string|max:20',
            'day_of_month' => 'required|string|max:20',
            'month' => 'required|string|max:20',
            'day_of_week' => 'required|string|max:20',
            'user' => 'nullable|string|max:50',
            'comment' => 'nullable|string|max:255',
        ]);

        CronJob::create($request->all());
        $schedule = implode(' ', [$request->minute, $request->hour, $request->day_of_month, $request->month, $request->day_of_week]);
        CronService::addJob($request->user ?? 'root', $schedule, $request->command);

        return back()->with('success', 'Cron job added.');
    }

    public function destroy(CronJob $cronJob)
    {
        $cronJob->delete();
        return back()->with('success', 'Cron job deleted.');
    }

    public function toggle(CronJob $cronJob)
    {
        $cronJob->update(['enabled' => !$cronJob->enabled]);
        $status = $cronJob->enabled ? 'enabled' : 'disabled';
        return back()->with('success', "Cron job {$status}.");
    }

    public function systemCron()
    {
        $jobs = CronService::parseJobs('root');
        $allCrontabs = CronService::getAllCrontabs();
        $daemon = CronService::cronDaemonStatus();
        return view('cron.system', compact('jobs', 'allCrontabs', 'daemon'));
    }

    public function saveSystemCron(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'content' => 'required|string',
        ]);
        CronService::saveCrontab($request->username, $request->content);
        return back()->with('success', 'Crontab saved.');
    }

    public function cronLog()
    {
        $log = CronService::getCronLog();
        return view('cron.log', compact('log'));
    }
}
