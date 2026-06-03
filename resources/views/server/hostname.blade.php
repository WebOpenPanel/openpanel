@extends('layouts.app')
@section('title', 'Server Hostname')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('server.hostname') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Hostname</a>
        <a href="{{ route('server.time') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Time</a>
        <a href="{{ route('server.services') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Services</a>
        <a href="{{ route('server.ssh-keys') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">SSH Keys</a>
        <a href="{{ route('server.yum') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Packages</a>
        <a href="{{ route('server.webserver') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Webserver</a>
        <a href="{{ route('server.php') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">PHP</a>
        <a href="{{ route('server.terminal') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Terminal</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-server mr-2 text-indigo-500"></i>Hostname</h3>
            <div class="text-lg font-bold text-gray-800 mb-3">{{ $hostname }}</div>
            <form method="POST" action="{{ route('server.set-hostname') }}" class="flex gap-2">@csrf
                <input type="text" name="hostname" value="{{ $hostname }}" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                <button class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Set</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-tachometer-alt mr-2 text-yellow-500"></i>Load Average</h3>
            <div class="text-sm text-gray-600">
                @if(is_array($loadAvg))
                    1m: {{ $loadAvg['1min'] ?? 'N/A' }} | 5m: {{ $loadAvg['5min'] ?? 'N/A' }} | 15m: {{ $loadAvg['15min'] ?? 'N/A' }}
                @else
                    {{ $loadAvg ?? 'N/A' }}
                @endif
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-clock mr-2 text-green-500"></i>Uptime</h3>
            <div class="text-sm text-gray-600">{{ $uptime ?? 'N/A' }}</div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Server Actions</h3>
        <div class="flex gap-3">
            <form method="POST" action="{{ route('server.reboot') }}" onsubmit="return confirm('Reboot server?')">@csrf<button class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700"><i class="fas fa-redo mr-1"></i>Reboot</button></form>
            <form method="POST" action="{{ route('server.shutdown') }}" onsubmit="return confirm('Shutdown server?')">@csrf<button class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700"><i class="fas fa-power-off mr-1"></i>Shutdown</button></form>
        </div>
    </div>
</div>
@endsection
