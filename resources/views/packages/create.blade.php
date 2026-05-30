@extends('layouts.app')
@section('title', 'Create Package')
@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('packages.store') }}" class="space-y-5">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Package Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Disk Space (MB)</label>
                    <input type="number" name="disk_space_mb" value="1024" required class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Bandwidth (MB)</label>
                    <input type="number" name="bandwidth_mb" value="10240" required class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Domains</label>
                    <input type="number" name="max_domains" value="1" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Emails</label>
                    <input type="number" name="max_email_accounts" value="10" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Databases</label>
                    <input type="number" name="max_databases" value="5" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Subdomains</label>
                    <input type="number" name="max_subdomains" value="5" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max FTP</label>
                    <input type="number" name="max_ftp_accounts" value="5" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Cron Jobs</label>
                    <input type="number" name="max_cron_jobs" value="5" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Web Server</label>
                    <select name="web_server" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                        <option value="apache">Apache</option><option value="nginx">Nginx</option><option value="litespeed">LiteSpeed</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">PHP Version</label>
                    <input type="text" name="php_version" placeholder="8.2" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div class="flex items-end gap-4 pb-1">
                    <label class="flex items-center"><input type="checkbox" name="shell_access" value="1" class="w-4 h-4 text-indigo-600 rounded"><span class="ml-2 text-sm text-gray-700">Shell</span></label>
                    <label class="flex items-center"><input type="checkbox" name="ssl_enabled" value="1" checked class="w-4 h-4 text-indigo-600 rounded"><span class="ml-2 text-sm text-gray-700">SSL</span></label>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-3 border-t">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-plus mr-2"></i> Create Package</button>
                <a href="{{ route('packages.index') }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
