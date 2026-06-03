@extends('layouts.app')
@section('title', 'Network Configuration')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Hostname</h3>
        <p class="text-sm mb-2">Current: <span class="font-bold">{{ $hostname }}</span></p>
        <form method="POST" action="{{ route('network.hostname') }}" class="flex gap-2">
            @csrf
            <input name="hostname" value="{{ $hostname }}" class="border rounded px-3 py-1 flex-1" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Update</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Network Interfaces</h3>
        <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="text-left py-2">Interface</th><th>Address</th></tr></thead>
            <tbody>
            @foreach($interfaces as $iface)
            <tr class="border-b"><td class="py-2">{{ $iface['name'] }}</td><td>{{ $iface['address'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">DNS Configuration</h3>
        <form method="POST" action="{{ route('network.dns') }}">
            @csrf
            <textarea name="dns" rows="6" class="w-full font-mono text-sm border rounded p-3">{{ $dns }}</textarea>
            <button class="mt-2 bg-blue-600 text-white px-3 py-1 rounded text-sm">Save</button>
        </form>
    </div>
</div>
@endsection
