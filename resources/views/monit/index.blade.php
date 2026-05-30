@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Monit</h1>
    @if(!$installed)<div class="bg-yellow-50 border border-yellow-200 rounded p-4">Monit is not installed.</div>
    <form method="POST" action="{{ route('monit.install') }}" class="mt-4">@csrf<button class="bg-green-600 text-white px-4 py-2 rounded">Install Monit</button></form>
    @else
    <div class="bg-white rounded-lg shadow p-6 mb-6"><h2 class="text-lg font-semibold mb-2">Status</h2><pre class="text-sm bg-gray-50 p-4 rounded max-h-64 overflow-auto">{{ $status }}</pre></div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Service Configs</h2>
        @foreach($configs as $cfg)<div class="border-b py-3 flex justify-between">
            <span>{{ $cfg['file'] }}</span>
            <div class="flex gap-2">
                <a href="{{ route('monit.config-edit', $cfg['file']) }}" class="text-blue-600 text-sm">Edit</a>
                <form method="POST" action="{{ route('monit.config-delete') }}">@csrf @method('DELETE')<input type="hidden" name="file" value="{{ $cfg['file'] }}"><button class="text-red-600 text-sm">Delete</button></form>
            </div>
        </div>@endforeach
    </div>
    @endif
</div>
@endsection