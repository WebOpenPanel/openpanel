@extends('layouts.app')

@section('content')
<div class="p-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">API Access</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    @if(session('new_token'))
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-4">
            <strong>Copy this token now — it will not be shown again:</strong>
            <code class="block mt-2 p-2 bg-gray-100 rounded text-sm break-all">{{ session('new_token') }}</code>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    {{-- Create Token --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Create API Token</h2>
        <form method="POST" action="{{ route('admin.api-tokens.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Token Name</label>
                    <input type="text" name="name" required class="w-full border rounded px-3 py-2" placeholder="e.g. WHMCS Production">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Allowed IPs (comma-separated, blank=all)</label>
                    <input type="text" name="allowed_ips" class="w-full border rounded px-3 py-2" placeholder="1.2.3.4, 5.6.7.8">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Expires At (optional)</label>
                    <input type="datetime-local" name="expires_at" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Scopes</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($scopes as $scope)
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="scopes[]" value="{{ $scope }}"
                                    {{ $scope === 'admin:all' ? 'checked' : '' }}
                                    class="mr-1">
                                <span class="text-xs">{{ $scope }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Token</button>
        </form>
    </div>

    {{-- Existing Tokens --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">API Tokens</h2>
        @if(empty($tokens))
            <p class="text-gray-500">No tokens created yet.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2">Name</th>
                        <th class="text-left py-2">Scopes</th>
                        <th class="text-left py-2">Allowed IPs</th>
                        <th class="text-left py-2">Last Used</th>
                        <th class="text-left py-2">Status</th>
                        <th class="text-left py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tokens as $t)
                        <tr class="border-b">
                            <td class="py-2 font-medium">{{ $t['name'] }}</td>
                            <td class="py-2">
                                @if(!empty($t['scopes']))
                                    @foreach($t['scopes'] as $s)
                                        <span class="inline-block bg-gray-200 rounded px-1 text-xs mr-1">{{ $s }}</span>
                                    @endforeach
                                @endif
                            </td>
                            <td class="py-2 text-xs">{{ $t['allowed_ips'] ? implode(', ', $t['allowed_ips']) : 'Any' }}</td>
                            <td class="py-2 text-xs">{{ $t['last_used_at'] ?? 'Never' }}</td>
                            <td class="py-2">
                                @if($t['is_active'])
                                    <span class="text-green-600 font-medium">Active</span>
                                @else
                                    <span class="text-red-600 font-medium">Revoked</span>
                                @endif
                            </td>
                            <td class="py-2">
                                @if($t['is_active'])
                                    <form method="POST" action="{{ route('admin.api-tokens.revoke', $t['id']) }}" class="inline">
                                        @csrf
                                        <button class="text-red-600 hover:underline text-xs">Revoke</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.api-tokens.reactivate', $t['id']) }}" class="inline">
                                        @csrf
                                        <button class="text-green-600 hover:underline text-xs">Reactivate</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.api-tokens.destroy', $t['id']) }}" class="inline ml-2">
                                    @csrf @method('DELETE')
                                    <button class="text-gray-600 hover:underline text-xs" onclick="return confirm('Delete this token?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Request Logs --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Recent API Requests</h2>
        @if(empty($logs))
            <p class="text-gray-500">No requests logged yet.</p>
        @else
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2">Time</th>
                        <th class="text-left py-2">Method</th>
                        <th class="text-left py-2">Path</th>
                        <th class="text-left py-2">Status</th>
                        <th class="text-left py-2">Duration</th>
                        <th class="text-left py-2">Token</th>
                        <th class="text-left py-2">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $l)
                        <tr class="border-b {{ ($l['status_code'] ?? 0) >= 400 ? 'bg-red-50' : '' }}">
                            <td class="py-1">{{ $l['created_at'] ?? '' }}</td>
                            <td class="py-1">{{ $l['method'] }}</td>
                            <td class="py-1 font-mono">{{ $l['path'] }}</td>
                            <td class="py-1">{{ $l['status_code'] }}</td>
                            <td class="py-1">{{ $l['duration_ms'] ? round($l['duration_ms'], 1) . 'ms' : '-' }}</td>
                            <td class="py-1">{{ $l['token']['name'] ?? '-' }}</td>
                            <td class="py-1">{{ $l['ip'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
