@extends('layouts.app')
@section('title', 'Netdata Monitor')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-chart-line mr-2"></i>Netdata Monitor</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold">Netdata Status</h2>
                <p class="text-sm text-gray-500 mt-1">Real-time performance and health monitoring</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 text-sm rounded {{ $installed ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ $installed ? 'INSTALLED' : 'NOT INSTALLED' }}</span>
                @if($installed)
                <span class="px-3 py-1 text-sm rounded {{ $running ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $running ? 'RUNNING' : 'STOPPED' }}</span>
                @endif
            </div>
        </div>
        <div class="mt-4 flex gap-4 flex-wrap">
            @if(!$installed)
            <form action="{{ route('netdata.install') }}" method="POST">@csrf<button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">Install Netdata</button></form>
            @else
            @if($running)
            <form action="{{ route('netdata.toggle') }}" method="POST">@csrf<input type="hidden" name="action" value="stop"><button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Stop</button></form>
            <form action="{{ route('netdata.toggle') }}" method="POST">@csrf<input type="hidden" name="action" value="restart"><button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Restart</button></form>
            <a href="http://{{ request()->server->get('SERVER_ADDR', '127.0.0.1') }}:{{ $port }}" target="_blank" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Open Dashboard</a>
            @else
            <form action="{{ route('netdata.toggle') }}" method="POST">@csrf<input type="hidden" name="action" value="start"><button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Start</button></form>
            @endif
            @endif
        </div>
    </div>
</div>
@endsection
