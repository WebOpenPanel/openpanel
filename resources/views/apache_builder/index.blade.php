@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Apache Builder</h1>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <p class="mb-2"><strong>Current:</strong> {{ $currentVersion }}</p>
        <form method="POST" action="{{ route('apache-builder.build') }}">@csrf
            <div class="mb-4"><label class="block text-sm font-medium">Version</label><select name="version" class="mt-1 block w-full border rounded p-2">@foreach($versions as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="mb-4"><label class="block text-sm font-medium">Configure Options</label><textarea name="addons" rows="10" class="mt-1 block w-full border rounded p-2 font-mono text-sm">{{ $defaultConfigure }}</textarea></div>
            <div class="mb-4"><label class="flex items-center"><input type="checkbox" name="mod_h264" value="1" class="mr-2"> Install mod_h264/flvx</label></div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Build Apache</button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-2">Loaded Modules</h2><pre class="text-sm bg-gray-50 p-4 rounded max-h-64 overflow-auto">{{ $modules }}</pre>
    </div>
</div>
@endsection
