@extends('layouts.app')
@section('title', 'FTP Sessions')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('ftp.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Users</a>
        <a href="{{ route('ftp.sessions') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Sessions</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-users mr-2 text-indigo-500"></i>Active FTP Sessions</h3>
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ $sessions }}</pre>
    </div>
</div>
@endsection
