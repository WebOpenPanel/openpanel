@extends('layouts.app')
@section('title', 'Package: ' . $package->name)
@section('content')
<div class="max-w-2xl space-y-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('packages.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-lg font-bold text-gray-900">{{ $package->name }}</h2>
        <a href="{{ route('packages.edit', $package) }}" class="text-sm text-indigo-600 hover:text-indigo-800"><i class="fas fa-edit mr-1"></i>Edit</a>
    </div>
    <div class="bg-white rounded-xl border p-6">
        <div class="grid grid-cols-2 gap-y-4 gap-x-8">
            <div><span class="text-xs text-gray-500 uppercase">Disk Space</span><p class="text-sm font-semibold">{{ $package->disk_space_formatted }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">Bandwidth</span><p class="text-sm font-semibold">{{ $package->bandwidth_formatted }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">Max Domains</span><p class="text-sm font-semibold">{{ $package->max_domains }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">Max Emails</span><p class="text-sm font-semibold">{{ $package->max_email_accounts }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">Max Databases</span><p class="text-sm font-semibold">{{ $package->max_databases }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">Max FTP</span><p class="text-sm font-semibold">{{ $package->max_ftp_accounts }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">Web Server</span><p class="text-sm font-semibold">{{ $package->web_server ?? 'Apache' }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">PHP Version</span><p class="text-sm font-semibold">{{ $package->php_version ?? 'Default' }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">Shell Access</span><p class="text-sm font-semibold">{{ $package->shell_access ? 'Yes' : 'No' }}</p></div>
            <div><span class="text-xs text-gray-500 uppercase">Accounts Using</span><p class="text-sm font-semibold">{{ $package->userAccounts->count() }}</p></div>
        </div>
    </div>
</div>
@endsection
