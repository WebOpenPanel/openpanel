@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Cluster Manager</h1>
    @if(!$configured)
    <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-4">Cluster not initialized.</div>
    <form method="POST" action="{{ route('cluster.init') }}">@csrf<button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Initialize Cluster</button></form>
    @else
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Servers</h2>
        <table class="w-full"><thead><tr class="border-b"><th class="text-left p-2">Name</th><th class="text-left p-2">IP</th><th class="text-left p-2">Status</th><th class="text-left p-2">Action</th></tr></thead>
        <tbody>@foreach($servers as $s)<tr class="border-b"><td class="p-2">{{ $s->name }}</td><td class="p-2">{{ $s->ip }}</td><td class="p-2">{{ $s->status }}</td><td class="p-2"><form method="POST" action="{{ route('cluster.remove-server', $s->id) }}">@csrf @method('DELETE')<button type="submit" class="text-red-600 text-sm">Remove</button></form></td></tr>@endforeach</tbody></table>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Add Server</h2>
        <form method="POST" action="{{ route('cluster.add-server') }}" class="grid grid-cols-3 gap-4">@csrf
            <input type="text" name="name" placeholder="Name" class="border rounded p-2" required>
            <input type="text" name="ip" placeholder="IP" class="border rounded p-2" required>
            <input type="text" name="apikey" placeholder="API Key" class="border rounded p-2" required>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded col-span-3">Add</button>
        </form>
    </div>
    @endif
</div>
@endsection
