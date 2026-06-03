@extends('layouts.app')
@section('title', 'Running Processes')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('server.hostname') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Hostname</a>
        <a href="{{ route('server.processes') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Processes</a>
        <a href="{{ route('server.network') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Network</a>
        <a href="{{ route('server.disk') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Disk</a>
        <a href="{{ route('server.time') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Time</a>
        <a href="{{ route('server.ssh-keys') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">SSH Keys</a>
        <a href="{{ route('server.yum') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Packages</a>
        <a href="{{ route('server.webserver') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Webserver</a>
        <a href="{{ route('server.php') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">PHP</a>
        <a href="{{ route('server.terminal') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Terminal</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b"><h3 class="text-sm font-semibold text-gray-700">Running Processes (sorted by CPU)</h3></div>
        @if(is_array($processes))
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50"><tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-500">USER</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500">PID</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500">CPU%</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500">MEM%</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500">STAT</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500">COMMAND</th>
                </tr></thead>
                <tbody class="divide-y">
                    @foreach($processes as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-1.5 font-mono">{{ $p['user'] ?? '' }}</td>
                        <td class="px-3 py-1.5 font-mono">{{ $p['pid'] ?? '' }}</td>
                        <td class="px-3 py-1.5 font-mono {{ ($p['cpu'] ?? 0) > 50 ? 'text-red-600 font-bold' : '' }}">{{ $p['cpu'] ?? '' }}</td>
                        <td class="px-3 py-1.5 font-mono">{{ $p['mem'] ?? '' }}</td>
                        <td class="px-3 py-1.5 font-mono">{{ $p['stat'] ?? '' }}</td>
                        <td class="px-3 py-1.5 font-mono max-w-xs truncate">{{ $p['command'] ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <pre class="p-5 text-sm font-mono max-h-96 overflow-auto">{{ $processes }}</pre>
        @endif
    </div>
</div>
@endsection
