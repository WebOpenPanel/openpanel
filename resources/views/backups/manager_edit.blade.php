@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Edit Backup #{{ $backup['id'] ?? '' }}</h1>
    <form method="POST" action="{{ route('backups.manager-save') }}" class="bg-white rounded-lg shadow p-6">@csrf
        <input type="hidden" name="ID" value="{{ $backup['id'] ?? '' }}">
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium">Connection Type</label><select name="CONNECTION_TYPE" class="mt-1 block w-full border rounded p-2"><option value="FTP" {{ ($backup['connection_type'] ?? '') === 'FTP' ? 'selected' : '' }}>FTP</option><option value="SFTP" {{ ($backup['connection_type'] ?? '') === 'SFTP' ? 'selected' : '' }}>SFTP</option><option value="LOCAL" {{ ($backup['connection_type'] ?? '') === 'LOCAL' ? 'selected' : '' }}>Local</option></select></div>
            <div><label class="block text-sm font-medium">FTP Server</label><input type="text" name="FTP_SERVERNAME" value="{{ $backup['ftp_server'] ?? '' }}" class="mt-1 block w-full border rounded p-2"></div>
            <div><label class="block text-sm font-medium">FTP User</label><input type="text" name="FTP_LOGIN_USER" value="{{ $backup['ftp_user'] ?? '' }}" class="mt-1 block w-full border rounded p-2"></div>
            <div><label class="block text-sm font-medium">FTP Location</label><input type="text" name="FTP_LOCATION" value="{{ $backup['ftp_location'] ?? '' }}" class="mt-1 block w-full border rounded p-2"></div>
            <div><label class="block text-sm font-medium">FTP Type</label><select name="FTP_TYPE" class="mt-1 block w-full border rounded p-2"><option value="ftp" {{ ($backup['ftp_type'] ?? '') === 'ftp' ? 'selected' : '' }}>FTP</option><option value="ftps" {{ ($backup['ftp_type'] ?? '') === 'ftps' ? 'selected' : '' }}>FTPS</option></select></div>
            <div><label class="block text-sm font-medium">Local Path</label><input type="text" name="LOCATION_LOCAL_FILE" value="{{ $backup['local_path'] ?? '/home/tmp_bak/' }}" class="mt-1 block w-full border rounded p-2"></div>
            <div><label class="block text-sm font-medium">Backup Status</label><select name="BACKUP_STATUS" class="mt-1 block w-full border rounded p-2"><option value="1" {{ ($backup['status'] ?? '') === 'enabled' ? 'selected' : '' }}>Enabled</option><option value="0" {{ ($backup['status'] ?? '') !== 'enabled' ? 'selected' : '' }}>Disabled</option></select></div>
            <div><label class="block text-sm font-medium">Hour</label><input type="text" name="CRON_HOUR" value="{{ $backup['cron_hour'] ?? '0' }}" class="mt-1 block w-full border rounded p-2"></div>
            <div><label class="block text-sm font-medium">Minutes</label><input type="text" name="CRON_MINUTES" value="{{ $backup['cron_minutes'] ?? '0' }}" class="mt-1 block w-full border rounded p-2"></div>
            <div><label class="block text-sm font-medium">Compression</label><select name="INCREMENTAL" class="mt-1 block w-full border rounded p-2"><option value="0" {{ ($backup['incremental'] ?? 0) == 0 ? 'selected' : '' }}>Full</option><option value="1" {{ ($backup['incremental'] ?? 0) == 1 ? 'selected' : '' }}>Incremental</option></select></div>
            <div><label class="block text-sm font-medium">Daily Retention</label><input type="number" name="BACKUP_RETENTION_DAILY" value="{{ $backup['retention_daily'] ?? 0 }}" class="mt-1 block w-full border rounded p-2"></div>
            <div><label class="block text-sm font-medium">Weekly Retention</label><input type="number" name="BACKUP_RETENTION_WEEKLY" value="{{ $backup['retention_weekly'] ?? 0 }}" class="mt-1 block w-full border rounded p-2"></div>
        </div>
        <button type="submit" class="mt-6 bg-blue-600 text-white px-4 py-2 rounded">Save</button>
    </form>
</div>
@endsection