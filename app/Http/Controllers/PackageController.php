<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::withCount('userAccounts')->latest()->paginate(20);
        return view('packages.index', compact('packages'));
    }

    public function create()
    {
        return view('packages.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:packages,name',
            'description' => 'nullable|string|max:255',
            'disk_space_mb' => 'required|integer|min:0',
            'bandwidth_mb' => 'required|integer|min:0',
            'max_domains' => 'required|integer|min:0',
            'max_subdomains' => 'nullable|integer|min:-1',
            'max_email_accounts' => 'nullable|integer|min:-1',
            'max_databases' => 'nullable|integer|min:-1',
            'max_ftp_accounts' => 'nullable|integer|min:-1',
            'max_parked_domains' => 'nullable|integer|min:-1',
            'max_addon_domains' => 'nullable|integer|min:-1',
            'max_email_lists' => 'nullable|integer|min:-1',
            'hourly_emails' => 'nullable|integer|min:0',
            'reseller' => 'nullable|string|max:100',
            'max_accounts' => 'nullable|integer|min:0',
            'cgroups' => 'nullable|string|max:40',
            'nproc' => 'nullable|integer|min:0',
            'apache_nproc' => 'nullable|integer|min:0',
            'inode' => 'nullable|integer|min:-1',
            'nofile' => 'nullable|integer|min:0',
            'nodejs_apps' => 'nullable|integer|min:0',
            'mongo_database' => 'nullable|integer|min:-1',
            'pgresql_database' => 'nullable|integer|min:-1',
            'tomcat_apps' => 'nullable|integer|min:0',
            'shell_access' => 'nullable|boolean',
            'ssl_enabled' => 'nullable|boolean',
            'max_cron_jobs' => 'nullable|integer|min:0',
            'php_version' => 'nullable|string|max:20',
            'web_server' => 'nullable|in:apache,nginx,litespeed',
        ]);

        Package::create($request->all());

        return redirect()->route('packages.index')
            ->with('success', "Package '{$request->name}' created successfully.");
    }

    public function show(Package $package)
    {
        $package->load('userAccounts');
        return view('packages.show', compact('package'));
    }

    public function edit(Package $package)
    {
        return view('packages.edit', compact('package'));
    }

    public function update(Request $request, Package $package)
    {
        $request->validate([
            'description' => 'nullable|string|max:255',
            'disk_space_mb' => 'required|integer|min:1',
            'bandwidth_mb' => 'required|integer|min:1',
            'max_domains' => 'required|integer|min:1',
        ]);

        $package->update($request->all());

        return redirect()->route('packages.show', $package)
            ->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package)
    {
        if ($package->userAccounts()->count() > 0) {
            return back()->with('error', 'Cannot delete package that is assigned to user accounts.');
        }

        $package->delete();
        return redirect()->route('packages.index')
            ->with('success', 'Package deleted successfully.');
    }
}
