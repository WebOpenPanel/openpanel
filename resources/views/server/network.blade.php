@extends('layouts.app')
@section('title', 'Network Configuration')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('server.hostname') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Hostname</a>
        <a href="{{ route('server.processes') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Processes</a>
        <a href="{{ route('server.network') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Network</a>
        <a href="{{ route('server.disk') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Disk</a>
        <a href="{{ route('server.time') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Time</a>
        <a href="{{ route('server.yum') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Packages</a>
        <a href="{{ route('server.webserver') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Webserver</a>
        <a href="{{ route('server.php') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">PHP</a>
        <a href="{{ route('server.terminal') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Terminal</a>
    </div>

    @if(isset($bandwidth) && is_array($bandwidth))
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-arrow-down mr-2 text-green-500"></i>RX (Received)</h3>
            <div class="text-2xl font-bold text-gray-800">{{ $bandwidth['rx_formatted'] ?? '0 B' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-arrow-up mr-2 text-blue-500"></i>TX (Sent)</h3>
            <div class="text-2xl font-bold text-gray-800">{{ $bandwidth['tx_formatted'] ?? '0 B' }}</div>
        </div>
    </div>
    @endif

    @if(isset($interfaces))
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-network-wired mr-2 text-indigo-500"></i>Interfaces</h3>
        @if(is_array($interfaces) && isset($interfaces['raw']))
            <pre class="text-sm font-mono max-h-64 overflow-auto bg-gray-50 p-3 rounded">{{ $interfaces['raw'] }}</pre>
        @elseif(is_array($interfaces))
            <pre class="text-sm font-mono max-h-64 overflow-auto bg-gray-50 p-3 rounded">{{ json_encode($interfaces, JSON_PRETTY_PRINT) }}</pre>
        @else
            <pre class="text-sm font-mono max-h-64 overflow-auto bg-gray-50 p-3 rounded">{{ $interfaces }}</pre>
        @endif
    </div>
    @endif

    @if(isset($netstat))
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-plug mr-2 text-yellow-500"></i>Active Connections</h3>
        <pre class="text-sm font-mono max-h-64 overflow-auto bg-gray-50 p-3 rounded">{{ $netstat }}</pre>
    </div>
    @endif
</div>
@endsection
