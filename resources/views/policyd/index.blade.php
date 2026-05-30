@extends('layouts.app')
@section('title', 'Policy Daemon')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Policy Daemon (Policyd)</h1>
    @if(!$status['installed'])
    <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-6">Policyd is not installed.</div>
    <form method="POST" action="{{ route('policyd.install') }}">@csrf<button class="bg-green-600 text-white px-6 py-2 rounded">Install Policyd</button></form>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Rate Limits</h2>
            <table class="w-full text-sm mb-4"><thead><tr class="border-b"><th>Track</th><th>Period</th><th>Max Msgs</th><th></th></tr></thead>
            <tbody>@foreach($rateLimits as $rl)<tr class="border-b"><td>{{ $rl['track'] }}</td><td>{{ $rl['period'] }}s</td><td>{{ $rl['max_messages'] }}</td><td><form method="POST" action="{{ route('policyd.rate-limit-delete', $rl['id']) }}">@csrf @method('DELETE')<button class="text-red-600 text-sm">Delete</button></form></td></tr>@endforeach</tbody></table>
            <form method="POST" action="{{ route('policyd.rate-limit') }}">@csrf
                <div class="grid grid-cols-4 gap-2"><input type="text" name="track" placeholder="SenderIP" class="border rounded p-2"><input type="number" name="period" placeholder="60" class="border rounded p-2"><input type="number" name="max_messages" placeholder="100" class="border rounded p-2"><input type="hidden" name="policy_id" value="1"><button class="bg-blue-600 text-white rounded">Add</button></div>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Policies</h2>
            @foreach($policies as $p)<div class="flex justify-between py-2 border-b"><span>{{ $p['name'] }}</span><span class="text-xs {{ $p['disabled'] ? 'text-red-500' : 'text-green-500' }}">{{ $p['disabled'] ? 'Disabled' : 'Enabled' }}</span></div>@endforeach
            <form method="POST" action="{{ route('policyd.restart') }}" class="mt-4">@csrf<button class="bg-yellow-600 text-white px-4 py-2 rounded">Restart Policyd</button></form>
        </div>
    </div>
    @endif
</div>
@endsection
