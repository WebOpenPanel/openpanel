<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Services\ShellService;
use App\Services\WebServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDomainController extends Controller
{
    protected function username(): string
    {
        return \Illuminate\Support\Facades\Auth::user()->username;
    }

    public function index()
    {
        $domains = DB::connection('openpanel')->table('domains')
            ->where('user', $this->username())
            ->get();

        return view('user-panel.domains.index', compact('domains'));
    }

    public function subdomains()
    {
        $subdomains = DB::connection('openpanel')->table('subdomains')
            ->where('user', $this->username())
            ->get();

        return view('user-panel.domains.subdomains', compact('subdomains'));
    }

    public function aliases()
    {
        $aliases = DB::connection('openpanel')->table('domains_alias')
            ->where('user', $this->username())
            ->get();

        return view('user-panel.domains.aliases', compact('aliases'));
    }

    public function addSubdomain(Request $request)
    {
        $request->validate([
            'subdomain' => 'required|string|regex:/^[a-z0-9\-]+$/',
            'domain' => 'required|string',
        ]);

        $sub = $request->subdomain;
        $domain = $request->domain;
        $fullDomain = "{$sub}.{$domain}";
        $username = $this->username();

        $docRoot = "/home/{$username}/web/{$fullDomain}/public_html";
        ShellService::exec("mkdir -p {$docRoot}");
        ShellService::exec("chown -R {$username}:{$username} /home/{$username}/web/{$fullDomain}");

        DB::connection('openpanel')->table('subdomains')->insert([
            'user' => $username,
            'domain' => $domain,
            'subdomain' => $sub,
            'path' => $docRoot,
            'created_at' => now(),
        ]);

        WebServerService::rebuildAll();

        return back()->with('success', "Subdomain {$fullDomain} created.");
    }

    public function removeSubdomain(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $subdomain = DB::connection('openpanel')->table('subdomains')
            ->where('id', $request->id)
            ->where('user', $this->username())
            ->first();

        if (!$subdomain) {
            return back()->with('error', 'Subdomain not found.');
        }

        DB::connection('openpanel')->table('subdomains')->where('id', $request->id)->delete();
        WebServerService::rebuildAll();

        return back()->with('success', 'Subdomain removed.');
    }

    public function addAlias(Request $request)
    {
        $request->validate([
            'alias' => 'required|string|regex:/^[a-z0-9\.\-]+$/',
            'domain' => 'required|string',
        ]);

        $username = $this->username();

        DB::connection('openpanel')->table('domains_alias')->insert([
            'user' => $username,
            'domain' => $request->domain,
            'alias' => $request->alias,
            'created_at' => now(),
        ]);

        WebServerService::rebuildAll();

        return back()->with('success', "Alias {$request->alias} added.");
    }

    public function removeAlias(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        DB::connection('openpanel')->table('domains_alias')
            ->where('id', $request->id)
            ->where('user', $this->username())
            ->delete();

        WebServerService::rebuildAll();

        return back()->with('success', 'Alias removed.');
    }
}
