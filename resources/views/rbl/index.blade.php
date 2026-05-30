@extends('layouts.app')
@section('title', 'RBL Check')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Real-time Blackhole List (RBL) Check</h1>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Check IP Address</h2>
            <form method="POST" action="{{ route('rbl.check') }}">@csrf
                <div class="flex gap-2"><input type="text" name="ip" placeholder="Enter IP address" class="border rounded p-2 flex-1" required><button class="bg-blue-600 text-white px-4 py-2 rounded">Check</button></div>
            </form>
            <form method="POST" action="{{ route('rbl.check-all') }}" class="mt-3">@csrf<button class="bg-purple-600 text-white px-4 py-2 rounded w-full">Check All Server IPs</button></form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Custom Blacklists</h2>
            <form method="POST" action="{{ route('rbl.add') }}" class="flex gap-2 mb-3">@csrf<input type="text" name="domain" placeholder="rbl.example.com" class="border rounded p-2 flex-1"><button class="bg-green-600 text-white px-3 py-2 rounded">Add</button></form>
            @foreach($customBlacklists as $bl)
            <div class="flex justify-between items-center py-1"><span class="font-mono text-sm">{{ $bl }}</span><form method="POST" action="{{ route('rbl.remove') }}">@csrf<input type="hidden" name="domain" value="{{ $bl }}"><button class="text-red-600 text-sm">Remove</button></form></div>
            @endforeach
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <h2 class="text-lg font-semibold mb-4">Monitored Blacklists ({{ count($blacklists) }})</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
            @foreach($blacklists as $bl)<span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ $bl }}</span>@endforeach
        </div>
    </div>
</div>
@endsection
