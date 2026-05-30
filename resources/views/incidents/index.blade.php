@extends('layouts.app')
@section('title', 'Incidents Log')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Security Incidents</h1>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4"><p class="text-sm text-gray-500">Total</p><p class="text-2xl font-bold">{{ $stats['total'] }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-sm text-gray-500">Unresolved</p><p class="text-2xl font-bold text-red-600">{{ $stats['unresolved'] }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-sm text-gray-500">Critical</p><p class="text-2xl font-bold text-red-800">{{ $stats['by_severity']['critical'] ?? 0 }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-sm text-gray-500">High</p><p class="text-2xl font-bold text-orange-600">{{ $stats['by_severity']['high'] ?? 0 }}</p></div>
    </div>
    <div class="flex gap-2 mb-4">
        <form method="POST" action="{{ route('incidents.scan') }}">@csrf<button class="bg-blue-600 text-white px-4 py-2 rounded">Scan Now</button></form>
        <form method="POST" action="{{ route('incidents.clear') }}">@csrf<button class="bg-red-600 text-white px-4 py-2 rounded" onclick="return confirm('Clear all incidents?')">Clear All</button></form>
    </div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 border-b"><th class="text-left px-4 py-3">Severity</th><th class="text-left px-4 py-3">Type</th><th class="text-left px-4 py-3">Title</th><th class="text-left px-4 py-3">Date</th><th class="text-right px-4 py-3">Actions</th></tr></thead>
            <tbody>
            @forelse($incidents as $inc)
            <tr class="border-b {{ $inc['resolved'] ?? false ? 'opacity-50' : '' }}">
                <td class="px-4 py-2"><span class="px-2 py-1 rounded text-xs {{ match($inc['severity'] ?? 'info') { 'critical' => 'bg-red-100 text-red-800', 'high' => 'bg-orange-100 text-orange-800', 'warning' => 'bg-yellow-100 text-yellow-800', default => 'bg-blue-100 text-blue-800' } }}">{{ $inc['severity'] ?? 'info' }}</span></td>
                <td class="px-4 py-2">{{ $inc['type'] ?? '-' }}</td>
                <td class="px-4 py-2">{{ $inc['title'] ?? '' }}<br><span class="text-xs text-gray-400">{{ $inc['description'] ?? '' }}</span></td>
                <td class="px-4 py-2 text-xs">{{ $inc['date'] ?? '' }}</td>
                <td class="px-4 py-2 text-right">
                    @if(!($inc['resolved'] ?? false))
                    <form method="POST" action="{{ route('incidents.resolve', $inc['id'] ?? '') }}" class="inline">@csrf<button class="text-green-600 text-sm mr-2">Resolve</button></form>
                    @endif
                    <form method="POST" action="{{ route('incidents.destroy', $inc['id'] ?? '') }}" class="inline">@csrf @method('DELETE')<button class="text-red-600 text-sm">Delete</button></form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No incidents.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
