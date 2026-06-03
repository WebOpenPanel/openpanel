@extends('layouts.app')
@section('title', 'Firewall')
@section('content')
<div class="space-y-6">
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800 font-medium">No firewall backend detected.</p>
        <p class="text-yellow-700 text-sm mt-1">Install firewalld for server protection.</p>
        <form method="POST" action="{{ route('firewall.install') }}" class="mt-3">@csrf
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Install Firewall</button>
        </form>
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Firewall Status</h3>
                <p class="text-xs text-gray-500 mt-1">Backend: <span class="font-mono font-medium">{{ $status['backend'] }}</span> — {{ $status['version'] }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if($active)
                    <span class="px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                @else
                    <span class="px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-800 rounded-full">Inactive</span>
                @endif
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <form method="POST" action="{{ route('firewall.toggle') }}">@csrf
                <input type="hidden" name="action" value="start">
                <button class="bg-green-600 text-white px-3 py-1.5 rounded text-xs hover:bg-green-700">Start</button>
            </form>
            <form method="POST" action="{{ route('firewall.toggle') }}">@csrf
                <input type="hidden" name="action" value="restart">
                <button class="bg-blue-600 text-white px-3 py-1.5 rounded text-xs hover:bg-blue-700">Restart</button>
            </form>
            <form method="POST" action="{{ route('firewall.toggle') }}">@csrf
                <input type="hidden" name="action" value="stop">
                <button class="bg-red-600 text-white px-3 py-1.5 rounded text-xs hover:bg-red-700">Stop</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Block IP</h3>
            <form method="POST" action="{{ route('firewall.block') }}" class="flex gap-2">
                @csrf
                <input name="ip" placeholder="IP Address" class="border rounded px-3 py-1.5 flex-1 text-sm" required>
                <button class="bg-red-600 text-white px-3 py-1.5 rounded text-xs hover:bg-red-700">Block</button>
            </form>
            @if(!empty($blocked))
            <div class="mt-3 space-y-1">
                @foreach(array_slice($blocked, 0, 20) as $ip)
                <div class="flex items-center justify-between text-xs bg-red-50 px-2 py-1 rounded">
                    <span class="font-mono">{{ trim($ip) }}</span>
                    <form method="POST" action="{{ route('firewall.unblock') }}" class="inline">@csrf
                        <input type="hidden" name="ip" value="{{ trim($ip) }}">
                        <button class="text-red-600 hover:text-red-800">Remove</button>
                    </form>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Allow IP</h3>
            <form method="POST" action="{{ route('firewall.allow') }}" class="flex gap-2">
                @csrf
                <input name="ip" placeholder="IP Address" class="border rounded px-3 py-1.5 flex-1 text-sm" required>
                <button class="bg-green-600 text-white px-3 py-1.5 rounded text-xs hover:bg-green-700">Allow</button>
            </form>
            @if(!empty($allowed))
            <div class="mt-3 space-y-1">
                @foreach(array_slice($allowed, 0, 20) as $ip)
                <div class="flex items-center justify-between text-xs bg-green-50 px-2 py-1 rounded">
                    <span class="font-mono">{{ trim($ip) }}</span>
                    <form method="POST" action="{{ route('firewall.remove-allow') }}" class="inline">@csrf
                        <input type="hidden" name="ip" value="{{ trim($ip) }}">
                        <button class="text-red-600 hover:text-red-800">Remove</button>
                    </form>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    @if(!empty($ports))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Open Ports</h3>
        <div class="flex flex-wrap gap-1.5">
            @foreach($ports as $port)
                <span class="px-2 py-0.5 text-xs font-mono bg-gray-100 text-gray-700 rounded">{{ trim($port) }}</span>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Raw Rules</h3>
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs max-h-64 overflow-auto whitespace-pre-wrap">{{ $rules }}</pre>
    </div>
    @endif
</div>
@endsection
