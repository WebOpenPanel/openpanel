@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Auto Update 3rd Party</h1>
    @if(session('output'))<pre class="bg-gray-900 text-green-400 p-4 rounded mb-4 text-sm overflow-auto">{{ session('output') }}</pre>@endif
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Configuration</h2>
        <form method="POST" action="{{ route('auto-update.save') }}">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium">Check Frequency</label><select name="frecuency" class="mt-1 block w-full border rounded p-2"><option value="daily" {{ ($config['frecuency'] ?? '') == 'daily' ? 'selected' : '' }}>Daily</option><option value="weekly" {{ ($config['frecuency'] ?? '') == 'weekly' ? 'selected' : '' }}>Weekly</option><option value="monthly" {{ ($config['frecuency'] ?? '') == 'monthly' ? 'selected' : '' }}>Monthly</option></select></div>
                <div><label class="block text-sm font-medium">Last Check</label><p class="mt-1">{{ $config['lastcheck'] ?? 'Never' }}</p></div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
        </form>
    </div>
    @if(isset($updates['updates']) && count($updates['updates']) > 0)
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Available Updates</h2>
        @foreach($updates['updates'] as $update)
        <div class="flex items-center justify-between border-b py-3">
            <span>{{ $update['name'] }}: {{ $update['current'] }} → {{ $update['available'] }}</span>
            <form method="POST" action="{{ $update['name'] === 'phpMyAdmin' ? route('auto-update.pma') : route('auto-update.roundcube') }}">@csrf<button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-sm">Update</button></form>
        </div>
        @endforeach
    </div>
    @else
    <div class="bg-green-50 border border-green-200 rounded p-4">All 3rd party software is up to date.</div>
    @endif
</div>
@endsection
