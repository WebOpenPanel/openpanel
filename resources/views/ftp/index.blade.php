@extends('layouts.app')
@section('title', 'FTP Accounts')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('ftp.index') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Users</a>
        <a href="{{ route('ftp.sessions') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Sessions</a>
        <a href="{{ route('ftp.config') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Config</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-lock mr-2 text-indigo-500"></i>FTPS Status</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
            <div><span class="text-gray-500">Service</span><div class="font-semibold">{{ $status['active'] ? 'active' : 'inactive' }}</div></div>
            <div><span class="text-gray-500">Explicit FTPS</span><div class="font-semibold">{{ $status['ftps_enabled'] ? 'enabled' : 'disabled' }}</div></div>
            <div><span class="text-gray-500">TLS mode</span><div class="font-semibold">{{ $status['tls_mode'] ?? '0' }}</div></div>
            <div><span class="text-gray-500">Passive range</span><div class="font-semibold">{{ $status['passive_range'] ?: 'not configured' }}</div></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-file-import mr-2 text-indigo-500"></i>Add FTP Account</h3>
        <form method="POST" action="{{ route('ftp.create') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            @csrf
            <input type="text" name="username" placeholder="Username" class="px-3 py-2 border rounded-lg text-sm" required>
            <input type="password" name="password" placeholder="Password" class="px-3 py-2 border rounded-lg text-sm" required>
            <input type="text" name="system_user" placeholder="System user" class="px-3 py-2 border rounded-lg text-sm" required>
            <div class="flex gap-2">
                <input type="text" name="path" placeholder="/home/user" class="flex-1 px-3 py-2 border rounded-lg text-sm" required>
                <button class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Add</button>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Username</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Path</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $u)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-sm font-medium">{{ $u['username'] }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-500 font-mono">{{ $u['path'] }}</td>
                    <td class="px-4 py-2.5">
                        <form method="POST" action="{{ route('ftp.destroy') }}" class="inline" onsubmit="return confirm('Delete?')">@csrf<input type="hidden" name="username" value="{{ $u['username'] }}"><button class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">Delete</button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">No FTP accounts</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
