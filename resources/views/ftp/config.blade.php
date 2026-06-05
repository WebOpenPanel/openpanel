@extends('layouts.app')
@section('title', 'FTP Configuration')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('ftp.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Users</a>
        <a href="{{ route('ftp.config') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Config</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-lock mr-2 text-indigo-500"></i>FTPS</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
            <div><span class="text-gray-500">Explicit FTPS</span><div class="font-semibold">{{ $status['ftps_enabled'] ? 'enabled' : 'disabled' }}</div></div>
            <div><span class="text-gray-500">Cert exists</span><div class="font-semibold">{{ $status['cert_exists'] ? 'yes' : 'no' }}</div></div>
            <div><span class="text-gray-500">Cert file</span><div class="font-mono text-xs break-all">{{ $status['cert_file'] }}</div></div>
            <div><span class="text-gray-500">Passive range</span><div class="font-semibold">{{ $status['passive_range'] ?: 'not configured' }}</div></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-cog mr-2 text-indigo-500"></i>pure-ftpd.conf</h3>
        <form method="POST" action="{{ route('ftp.save-config') }}" class="space-y-3">
            @csrf
            <textarea name="content" rows="25" class="w-full px-3 py-2 border rounded-lg text-sm font-mono">{{ $conf }}</textarea>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save Configuration</button>
        </form>
    </div>
</div>
@endsection
