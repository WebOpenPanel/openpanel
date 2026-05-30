@extends('layouts.app')
@section('title', 'Login Security')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.login-security') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Login Security</a>
        <a href="{{ route('security.shell-access') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Shell Access</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-user-lock mr-2 text-red-500"></i>Failed Login Attempts</h3>
        <table class="w-full">
            <thead class="border-b"><tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">IP</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Time</th>
            </tr></thead>
            <tbody class="divide-y">
                @forelse($failedLogins as $login)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-medium">{{ $login['user'] }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500 font-mono">{{ $login['ip'] }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ $login['time'] }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">No failed logins</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Currently Logged In</h3>
        <pre class="bg-gray-50 p-3 rounded text-xs overflow-auto max-h-48 font-mono">{{ $loggedInUsers }}</pre>
    </div>
</div>
@endsection
