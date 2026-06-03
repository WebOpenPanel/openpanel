@extends('layouts.app')
@section('title', 'ModSecurity')
@section('content')
<div class="space-y-6">
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800">ModSecurity is not installed.</p>
        <form method="POST" action="{{ route('modsecurity.install') }}" class="mt-2">@csrf
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Install ModSecurity</button>
        </form>
    </div>
    @else
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">ModSecurity Engine</h3>
            <form method="POST" action="{{ route('modsecurity.toggle') }}">
                @csrf
                <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
                <button class="px-4 py-2 rounded text-sm {{ $enabled ? 'bg-red-600 text-white' : 'bg-green-600 text-white' }}">
                    {{ $enabled ? 'Disable' : 'Enable' }}
                </button>
            </form>
        </div>
        <p class="text-sm text-gray-600">Status: <span class="font-bold {{ $enabled ? 'text-green-600' : 'text-red-600' }}">{{ $enabled ? 'Active' : 'Inactive' }}</span></p>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Rulesets</h3>
        @forelse($rulesets as $rule)
        <div class="border-b py-2 text-sm">{{ $rule }}</div>
        @empty
        <p class="text-gray-500 text-sm">No rulesets found.</p>
        @endforelse
        <form method="POST" action="{{ route('modsecurity.update-rules') }}" class="mt-3">@csrf
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Update Rules (git pull)</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Recent Logs</h3>
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs max-h-64 overflow-auto">{{ implode("\n", array_slice($logs, -30)) }}</pre>
    </div>
    @endif
</div>
@endsection
