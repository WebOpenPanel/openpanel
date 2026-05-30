@extends('layouts.app')
@section('title', 'Python Manager')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Python Manager</h1>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Installed Versions</h2>
            @forelse($installed as $v)
            <div class="flex justify-between items-center py-2 border-b">
                <span class="font-mono">Python {{ $v['version'] }}</span>
                <form method="POST" action="{{ route('python.remove') }}">@csrf<input type="hidden" name="version" value="{{ $v['version'] }}"><button class="text-red-600 text-sm">Remove</button></form>
            </div>
            @empty
            <p class="text-gray-500">No custom Python versions installed.</p>
            @endforelse
            <h3 class="text-md font-semibold mt-6 mb-3">System Python</h3>
            @foreach($system as $v)
            <div class="py-2 border-b"><span class="font-mono">Python {{ $v['version'] }}</span> <span class="text-xs text-gray-400">{{ $v['path'] }}</span></div>
            @endforeach
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Install Python</h2>
            <form method="POST" action="{{ route('python.install') }}">@csrf
                <label class="block text-sm font-medium mb-1">Version</label>
                <select name="version" class="w-full border rounded p-2 mb-3">
                    @foreach($available as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                </select>
                <button class="bg-blue-600 text-white px-4 py-2 rounded">Install</button>
            </form>
            <h2 class="text-lg font-semibold mt-6 mb-4">Set User Python Version</h2>
            <form method="POST" action="{{ route('python.set-user') }}">@csrf
                <label class="block text-sm font-medium mb-1">Username</label>
                <input type="text" name="user" class="w-full border rounded p-2 mb-3" required>
                <label class="block text-sm font-medium mb-1">Version</label>
                <select name="version" class="w-full border rounded p-2 mb-3">
                    @foreach($available as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                </select>
                <button class="bg-green-600 text-white px-4 py-2 rounded">Set Version</button>
            </form>
        </div>
    </div>
</div>
@endsection
