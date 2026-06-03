@extends('layouts.app')
@section('title', 'Login Security')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Active Sessions</h3>
        <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="text-left py-2">User</th><th>TTY</th><th>From</th><th>Action</th></tr></thead>
            <tbody>
            @foreach($activeUsers as $u)
            <tr class="border-b">
                <td class="py-2">{{ $u['user'] }}</td><td>{{ $u['tty'] }}</td><td>{{ $u['from'] }}</td>
                <td>
                    <form method="POST" action="{{ route('login-security.kick') }}" class="inline">@csrf
                        <input type="hidden" name="tty" value="{{ $u['tty'] }}">
                        <button class="text-red-600 text-sm">Kick</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Block IP</h3>
        <form method="POST" action="{{ route('login-security.block') }}" class="flex gap-2">
            @csrf
            <input name="ip" placeholder="IP Address" class="border rounded px-3 py-1 flex-1" required>
            <button class="bg-red-600 text-white px-3 py-1 rounded text-sm">Block</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Recent Failed Logins</h3>
        <pre class="bg-gray-900 text-red-400 p-3 rounded text-xs max-h-48 overflow-auto">{{ implode("\n", $failedLogins) }}</pre>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Last Logins</h3>
        <pre class="bg-gray-100 p-3 rounded text-xs max-h-48 overflow-auto">{{ implode("\n", $lastLogins) }}</pre>
    </div>
</div>
@endsection
