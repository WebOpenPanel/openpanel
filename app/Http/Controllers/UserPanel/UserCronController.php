<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Http\Request;

class UserCronController extends Controller
{
    protected function username(): string
    {
        return auth()->user()->username;
    }

    public function index()
    {
        $username = $this->username();
        $cronFile = "/var/spool/cron/{$username}";
        $jobs = [];

        if (file_exists($cronFile)) {
            $lines = file($cronFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $i => $line) {
                if (str_starts_with($line, '#')) continue;
                $parts = preg_split('/\s+/', $line, 6);
                if (count($parts) >= 6) {
                    $jobs[] = [
                        'line' => $i,
                        'minute' => $parts[0],
                        'hour' => $parts[1],
                        'day' => $parts[2],
                        'month' => $parts[3],
                        'weekday' => $parts[4],
                        'command' => $parts[5],
                        'raw' => $line,
                    ];
                }
            }
        }

        return view('user-panel.cron.index', compact('jobs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'minute' => 'required|string|max:20',
            'hour' => 'required|string|max:20',
            'day' => 'required|string|max:20',
            'month' => 'required|string|max:20',
            'weekday' => 'required|string|max:20',
            'command' => 'required|string',
        ]);

        $username = $this->username();
        $cronFile = "/var/spool/cron/{$username}";
        $line = implode(' ', [
            $request->minute,
            $request->hour,
            $request->day,
            $request->month,
            $request->weekday,
            $request->command,
        ]);

        file_put_contents($cronFile, $line . "\n", FILE_APPEND);

        return back()->with('success', 'Cron job added.');
    }

    public function destroy(Request $request)
    {
        $request->validate(['line' => 'required|integer']);

        $username = $this->username();
        $cronFile = "/var/spool/cron/{$username}";

        if (!file_exists($cronFile)) {
            return back()->with('error', 'No cron file found.');
        }

        $lines = file($cronFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lineNum = $request->line;

        if (!isset($lines[$lineNum])) {
            return back()->with('error', 'Cron job not found.');
        }

        unset($lines[$lineNum]);
        file_put_contents($cronFile, implode("\n", $lines) . "\n");

        return back()->with('success', 'Cron job removed.');
    }
}
