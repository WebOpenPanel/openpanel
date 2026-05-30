@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Node.js Apps</h1>
    <div class="bg-white rounded-lg shadow p-6">
        @foreach($apps as $app)<div class="border-b py-3 flex justify-between items-center">
            <div><strong>{{ $app->name ?? 'App' }}</strong> — v{{ $app->version ?? '?' }} — <span class="px-2 py-0.5 rounded text-xs {{ ($app->status ?? '') === 'running' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $app->status ?? 'unknown' }}</span></div>
            <div class="flex gap-2">
                @if(($app->status ?? '') !== 'running')<form method="POST" action="{{ route('nodejs.app-status') }}">@csrf<input type="hidden" name="action" value="start"><input type="hidden" name="app_name" value="{{ $app->name }}"><button class="text-green-600 text-sm">Start</button></form>@endif
                @if(($app->status ?? '') === 'running')<form method="POST" action="{{ route('nodejs.app-status') }}">@csrf<input type="hidden" name="action" value="stop"><input type="hidden" name="app_name" value="{{ $app->name }}"><button class="text-red-600 text-sm">Stop</button></form>
                <form method="POST" action="{{ route('nodejs.app-status') }}">@csrf<input type="hidden" name="action" value="restart"><input type="hidden" name="app_name" value="{{ $app->name }}"><button class="text-yellow-600 text-sm">Restart</button></form>@endif
            </div>
        </div>@endforeach
        @if(empty($apps))<p class="text-gray-500">No Node.js apps configured.</p>@endif
    </div>
</div>
@endsection