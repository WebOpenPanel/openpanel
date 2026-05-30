@extends('layouts.app')
@section('title', 'Backup Configuration')
@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('backups.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-lg font-bold">Backup Configuration</h2>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('backups.save-config') }}" class="space-y-5">
            @csrf
            <label class="flex items-center gap-2">
                <input type="checkbox" name="enabled" value="1" {{ $config->enabled ?? true ? 'checked' : '' }} class="w-4 h-4 text-indigo-600 rounded">
                <span class="text-sm font-medium text-gray-700">Enable Backups</span>
            </label>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Frequency</label>
                    <select name="frequency" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                        <option value="daily" {{ ($config->frequency ?? '')=='daily'?'selected':'' }}>Daily</option>
                        <option value="weekly" {{ ($config->frequency ?? '')=='weekly'?'selected':'' }}>Weekly</option>
                        <option value="monthly" {{ ($config->frequency ?? '')=='monthly'?'selected':'' }}>Monthly</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Retention (days)</label>
                    <input type="number" name="retention_days" value="{{ $config->retention_days ?? 30 }}" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            </div>
            <div class="space-y-2">
                <label class="flex items-center gap-2"><input type="checkbox" name="include_databases" value="1" {{ ($config->include_databases ?? true)?'checked':'' }} class="w-4 h-4 text-indigo-600 rounded"><span class="text-sm text-gray-700">Include Databases</span></label>
                <label class="flex items-center gap-2"><input type="checkbox" name="include_email" value="1" {{ ($config->include_email ?? true)?'checked':'' }} class="w-4 h-4 text-indigo-600 rounded"><span class="text-sm text-gray-700">Include Email</span></label>
                <label class="flex items-center gap-2"><input type="checkbox" name="include_files" value="1" {{ ($config->include_files ?? true)?'checked':'' }} class="w-4 h-4 text-indigo-600 rounded"><span class="text-sm text-gray-700">Include Files</span></label>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Destination</label>
                <select name="destination" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                    <option value="local" {{ ($config->destination ?? '')=='local'?'selected':'' }}>Local</option>
                    <option value="remote" {{ ($config->destination ?? '')=='remote'?'selected':'' }}>Remote (SCP/SFTP)</option>
                    <option value="ftp" {{ ($config->destination ?? '')=='ftp'?'selected':'' }}>FTP</option>
                    <option value="s3" {{ ($config->destination ?? '')=='s3'?'selected':'' }}>S3</option>
                </select></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Remote Host</label><input type="text" name="remote_host" value="{{ $config->remote_host ?? '' }}" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Remote User</label><input type="text" name="remote_user" value="{{ $config->remote_user ?? '' }}" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Notification Email</label>
                <input type="email" name="notification_email" value="{{ $config->notification_email ?? '' }}" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-save mr-2"></i> Save Configuration</button>
        </form>
    </div>
</div>
@endsection
