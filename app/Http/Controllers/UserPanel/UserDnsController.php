<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDnsController extends Controller
{
    protected function username(): string
    {
        return \Illuminate\Support\Facades\Auth::user()->username;
    }

    public function index()
    {
        $username = $this->username();
        $domains = DB::connection('mysql')->table('domains')
            ->where('user', $username)
            ->get();

        return view('user-panel.dns.index', compact('domains'));
    }

    public function show(string $domain)
    {
        $username = $this->username();
        $owned = DB::connection('mysql')->table('domains')
            ->where('user', $username)
            ->where('domain', $domain)
            ->exists();

        if (!$owned) {
            return back()->with('error', 'Domain not owned by you.');
        }

        $zoneFile = "/var/named/{$domain}.db";
        $records = [];

        if (file_exists($zoneFile)) {
            $lines = file($zoneFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with($line, ';') || str_starts_with($line, '$')) continue;
                if (preg_match('/^(\S+)\s+(\d+)\s+(IN)\s+(A|AAAA|CNAME|MX|TXT|NS|SRV|PTR)\s+(.+)$/', $line, $m)) {
                    $records[] = [
                        'name' => $m[1],
                        'ttl' => $m[2],
                        'type' => $m[4],
                        'value' => trim($m[5]),
                    ];
                }
            }
        }

        return view('user-panel.dns.show', compact('domain', 'records', 'zoneFile'));
    }

    public function addRecord(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'name' => 'required|string',
            'type' => 'required|in:A,AAAA,CNAME,MX,TXT,NS,SRV',
            'value' => 'required|string',
            'ttl' => 'integer|min:60|max:86400',
        ]);

        $username = $this->username();
        $owned = DB::connection('mysql')->table('domains')
            ->where('user', $username)
            ->where('domain', $request->domain)
            ->exists();

        if (!$owned) {
            return back()->with('error', 'Domain not owned by you.');
        }

        $domain = $request->domain;
        $zoneFile = "/var/named/{$domain}.db";

        $ttl = $request->ttl ?? 14400;
        $name = $request->name;
        $type = $request->type;
        $value = $request->value;

        if ($type === 'MX') {
            $line = "{$name}\t{$ttl}\tIN\tMX\t10\t{$value}";
        } else {
            $line = "{$name}\t{$ttl}\tIN\t{$type}\t{$value}";
        }

        file_put_contents($zoneFile, $line . "\n", FILE_APPEND);

        ShellService::exec("systemctl reload named 2>/dev/null || systemctl reload bind9 2>/dev/null");

        return back()->with('success', "DNS record added for {$domain}.");
    }

    public function deleteRecord(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'line_number' => 'required|integer',
        ]);

        $username = $this->username();
        $owned = DB::connection('mysql')->table('domains')
            ->where('user', $username)
            ->where('domain', $request->domain)
            ->exists();

        if (!$owned) {
            return back()->with('error', 'Domain not owned by you.');
        }

        $zoneFile = "/var/named/{$request->domain}.db";

        if (!file_exists($zoneFile)) {
            return back()->with('error', 'Zone file not found.');
        }

        $lines = file($zoneFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lineNum = $request->line_number;

        if (!isset($lines[$lineNum])) {
            return back()->with('error', 'Record not found.');
        }

        unset($lines[$lineNum]);
        file_put_contents($zoneFile, implode("\n", $lines) . "\n");

        ShellService::exec("systemctl reload named 2>/dev/null || systemctl reload bind9 2>/dev/null");

        return back()->with('success', 'DNS record deleted.');
    }
}
