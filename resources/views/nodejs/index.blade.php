@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Node.js Manager</h1>
    @if(!$installed)<div class="bg-yellow-50 border border-yellow-200 rounded p-4">Node.js Manager not installed.</div>
    <form method="POST" action="{{ route('nodejs.install') }}" class="mt-4">@csrf<button class="bg-green-600 text-white px-4 py-2 rounded">Install</button></form>
    @else
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Installed Versions</h2>
        @foreach($versions as $v)<div class="flex justify-between border-b py-2">
            <span>v{{ $v['ver'] }} {{ $v['default'] ? '(default)' : '' }} — {{ $v['qty'] }} apps</span>
            <div class="flex gap-2">
                @if(!$v['default'])<form method="POST" action="{{ route('nodejs.set-default') }}">@csrf<input type="hidden" name="version" value="{{ $v['ver'] }}"><button class="text-blue-600 text-sm">Set Default</button></form>@endif
                <form method="POST" action="{{ route('nodejs.uninstall-version') }}">@csrf<input type="hidden" name="version" value="{{ $v['ver'] }}"><button class="text-red-600 text-sm">Remove</button></form>
            </div>
        </div>@endforeach
        <form method="POST" action="{{ route('nodejs.install-version') }}" class="mt-4 flex gap-2">@csrf
            <input type="text" name="version" placeholder="Version (e.g. 18, 20, 22)" class="border rounded p-2 flex-1">
            <button class="bg-green-600 text-white px-4 py-2 rounded">Install Version</button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">User Config</h2>
        <form method="POST" action="{{ route('nodejs.save-config') }}">@csrf
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium">Port Range Start</label><input type="number" name="port_range_start" value="{{ $config->node_config->port_range[0] ?? 50000 }}" class="mt-1 block w-full border rounded p-2"></div>
                <div><label class="block text-sm font-medium">Port Range End</label><input type="number" name="port_range_end" value="{{ $config->node_config->port_range[1] ?? 60000 }}" class="mt-1 block w-full border rounded p-2"></div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">Save Config</button>
        </form>
    </div>
    @endif
</div>
@endsection