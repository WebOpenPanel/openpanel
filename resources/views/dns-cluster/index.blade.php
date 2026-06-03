@extends('layouts.app')
@section('title', 'DNS Cluster')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Add Slave DNS Server</h3>
        <form method="POST" action="{{ route('dns-cluster.add') }}" class="space-y-2">
            @csrf
            <div class="flex gap-2">
                <input name="host" placeholder="Slave IP/Hostname" class="border rounded px-3 py-1 flex-1" required>
                <input name="key" placeholder="RNDC Key" class="border rounded px-3 py-1 flex-1" required>
                <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Add</button>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Slave Servers</h3>
        @forelse($slaves as $s)
        <div class="flex items-center justify-between border-b py-2">
            <span class="text-sm">{{ $s['host'] }}</span>
            <form method="POST" action="{{ route('dns-cluster.remove') }}">@csrf
                <input type="hidden" name="host" value="{{ $s['host'] }}">
                <button class="text-red-600 text-sm">Remove</button>
            </form>
        </div>
        @empty
        <p class="text-gray-500 text-sm">No slave servers configured.</p>
        @endforelse
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Sync Zone</h3>
        <form method="POST" action="{{ route('dns-cluster.sync') }}" class="flex gap-2">
            @csrf
            <select name="domain" class="border rounded px-3 py-1 flex-1">
                @foreach($zones as $z)
                <option value="{{ $z }}">{{ $z }}</option>
                @endforeach
            </select>
            <button class="bg-green-600 text-white px-3 py-1 rounded text-sm">Sync to All Slaves</button>
        </form>
    </div>
</div>
@endsection
