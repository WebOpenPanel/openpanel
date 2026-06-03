@extends('layouts.app')
@section('title', 'Process Monitor')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Process Monitor</h2>
        <div class="text-sm text-gray-500">Load: {{ number_format($load[0], 2) }} / {{ number_format($load[1], 2) }} / {{ number_format($load[2], 2) }}</div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-xs">
            <thead><tr class="bg-gray-50 border-b">
                <th class="text-left py-2 px-2">User</th><th>PID</th><th>CPU%</th><th>MEM%</th><th>RSS</th><th>Stat</th>
                <th class="text-left">Command</th><th>Action</th>
            </tr></thead>
            <tbody>
            @foreach($processes as $p)
            <tr class="border-b hover:bg-gray-50">
                <td class="py-1 px-2">{{ $p['user'] }}</td><td class="text-center">{{ $p['pid'] }}</td>
                <td class="text-center">{{ $p['cpu'] }}</td><td class="text-center">{{ $p['mem'] }}</td>
                <td class="text-center">{{ $p['rss'] }}</td><td class="text-center">{{ $p['stat'] }}</td>
                <td class="truncate max-w-xs">{{ Str::limit($p['command'], 80) }}</td>
                <td>
                    <form method="POST" action="{{ route('process-monitor.kill') }}" class="inline">@csrf
                        <input type="hidden" name="pid" value="{{ $p['pid'] }}">
                        <button class="text-red-600" title="Kill">✕</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
