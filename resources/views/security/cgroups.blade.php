@extends('layouts.app')
@section('title', 'Cgroups')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.cgroups') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Cgroups</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-microchip mr-2 text-indigo-500"></i>Cgroups Status</h3>
        <div class="flex items-center gap-4 mb-4">
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ ($status['cgconfig'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">cgconfig: {{ ($status['cgconfig'] ?? false) ? 'Active' : 'Inactive' }}</span>
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ ($status['cgred'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">cgred: {{ ($status['cgred'] ?? false) ? 'Active' : 'Inactive' }}</span>
        </div>
        <form method="POST" action="{{ route('security.cgroups-action') }}">@csrf<button name="action" value="restart" class="px-3 py-1.5 bg-yellow-600 text-white rounded-lg text-xs hover:bg-yellow-700">Restart Cgroups</button></form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Set User Limits</h3>
        <form method="POST" action="{{ route('security.cgroups-limit') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Username</label><input type="text" name="username" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">CPU Quota (%)</label><input type="text" name="limits[cpu]" placeholder="e.g. 50000" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Memory Limit</label><input type="text" name="limits[memory]" placeholder="e.g. 512M" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Set Limits</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Configuration</h3>
        <pre class="bg-gray-50 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ $conf }}</pre>
    </div>
</div>
@endsection
