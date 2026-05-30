@extends('layouts.app')
@section('title', 'Bandwidth Monitor')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Bandwidth Monitor</h1>
    <div class="flex gap-2 mb-6">
        <a href="{{ route('bandwidth.index', ['period' => 'today']) }}" class="px-3 py-1 rounded {{ $period === 'today' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">Today</a>
        <a href="{{ route('bandwidth.index', ['period' => 'month']) }}" class="px-3 py-1 rounded {{ $period === 'month' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">This Month</a>
        <a href="{{ route('bandwidth.index', ['period' => 'total']) }}" class="px-3 py-1 rounded {{ $period === 'total' ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">Total</a>
    </div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 border-b"><th class="text-left px-4 py-3">User</th><th class="text-right px-4 py-3">In</th><th class="text-right px-4 py-3">Out</th><th class="text-right px-4 py-3">Total</th></tr></thead>
            <tbody>
            @forelse($usage as $u)
            <tr class="border-b hover:bg-gray-50"><td class="px-4 py-2"><a href="{{ route('bandwidth.user', ['user' => $u['username'], 'period' => $period]) }}" class="text-blue-600">{{ $u['username'] }}</a></td><td class="px-4 py-2 text-right font-mono">{{ \App\Services\BandwidthService::formatBytes($u['in_bytes']) }}</td><td class="px-4 py-2 text-right font-mono">{{ \App\Services\BandwidthService::formatBytes($u['out_bytes']) }}</td><td class="px-4 py-2 text-right font-mono font-bold">{{ \App\Services\BandwidthService::formatBytes($u['total_bytes']) }}</td></tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No bandwidth data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
