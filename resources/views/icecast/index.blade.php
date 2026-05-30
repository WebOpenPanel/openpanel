@extends('layouts.app')
@section('title', 'Icecast Streaming')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Icecast Streaming Server</h1>
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-6">Icecast is not installed.</div>
    <form method="POST" action="{{ route('icecast.install') }}">@csrf<button class="bg-green-600 text-white px-6 py-2 rounded">Install Icecast</button></form>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Options</h2>
            <form method="POST" action="{{ route('icecast.options') }}">@csrf
                <label class="flex items-center gap-2 py-2"><input type="checkbox" name="enabled" value="1" {{ ($options['enabled'] ?? 0) ? 'checked' : '' }}><span>Enable Icecast for Users</span></label>
                <div class="grid grid-cols-2 gap-3 mt-2">
                    <div><label class="block text-sm mb-1">Port Range Min</label><input type="number" name="port_range_min" value="{{ $options['port_range_min'] ?? 17000 }}" class="w-full border rounded p-2"></div>
                    <div><label class="block text-sm mb-1">Port Range Max</label><input type="number" name="port_range_max" value="{{ $options['port_range_max'] ?? 18000 }}" class="w-full border rounded p-2"></div>
                </div>
                <button class="mt-3 bg-blue-600 text-white px-4 py-2 rounded">Save Options</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Add Server</h2>
            <form method="POST" action="{{ route('icecast.add') }}">@csrf
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm mb-1">User</label><input type="text" name="user" class="w-full border rounded p-2" required></div>
                    <div><label class="block text-sm mb-1">Port</label><input type="number" name="port" class="w-full border rounded p-2" required></div>
                    <div><label class="block text-sm mb-1">Max Listeners</label><input type="number" name="listens" value="100" class="w-full border rounded p-2"></div>
                    <div><label class="block text-sm mb-1">Max Sources</label><input type="number" name="sources" value="5" class="w-full border rounded p-2"></div>
                </div>
                <button class="mt-3 bg-green-600 text-white px-4 py-2 rounded">Create Server</button>
            </form>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <h2 class="text-lg font-semibold p-6 pb-0">Servers</h2>
        <table class="w-full text-sm mt-4">
            <thead><tr class="bg-gray-50 border-b"><th class="text-left px-4 py-3">User</th><th class="text-left px-4 py-3">Port</th><th class="text-center px-4 py-3">Status</th><th class="text-right px-4 py-3">Actions</th></tr></thead>
            <tbody>
            @forelse($servers as $s)
            <tr class="border-b"><td class="px-4 py-2">{{ $s['user'] }}</td><td class="px-4 py-2 font-mono">{{ $s['port'] }}</td><td class="px-4 py-2 text-center"><span class="px-2 py-1 rounded text-xs bg-gray-100">-</span></td><td class="px-4 py-2 text-right"><form method="POST" action="{{ route('icecast.start', $s['port']) }}" class="inline">@csrf<button class="text-green-600 text-sm mr-1">Start</button></form><form method="POST" action="{{ route('icecast.stop', $s['port']) }}" class="inline">@csrf<button class="text-yellow-600 text-sm mr-1">Stop</button></form><form method="POST" action="{{ route('icecast.remove', $s['port']) }}" class="inline">@csrf @method('DELETE')<button class="text-red-600 text-sm">Delete</button></form></td></tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No servers.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
