@extends('layouts.app')
@section('title', 'Varnish Cache')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Varnish Cache</h1>

    @if(session('output'))
    <div class="bg-gray-900 text-green-400 p-4 rounded mb-6 font-mono text-sm overflow-x-auto">
        <pre>{{ session('output') }}</pre>
    </div>
    @endif

    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-6">
        Varnish Cache is not installed on this server.
    </div>
    <form method="POST" action="{{ route('varnish.install') }}" class="mb-6">
        @csrf
        <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">Install Varnish</button>
    </form>
    @else
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Status</h3>
            @if($status['running'])
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">Running</span>
            @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">Stopped</span>
            @endif
            @if(!empty($status['version']))
                <p class="text-xs text-gray-400 mt-2">{{ $status['version'] }}</p>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 mb-3">Service Control</h3>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('varnish.start') }}">@csrf<button class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">Start</button></form>
                <form method="POST" action="{{ route('varnish.stop') }}">@csrf<button class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">Stop</button></form>
                <form method="POST" action="{{ route('varnish.restart') }}">@csrf<button class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-sm">Restart</button></form>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 mb-3">Cache</h3>
            <form method="POST" action="{{ route('varnish.clear-cache') }}">
                @csrf
                <button class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded text-sm">Purge All Cache</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Varnish Parameters</h2>
            <form method="POST" action="{{ route('varnish.config') }}">
                @csrf
                <textarea name="config" rows="12" class="w-full border rounded p-3 font-mono text-sm">{{ $config }}</textarea>
                <button class="mt-3 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">Save Parameters</button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Default VCL</h2>
            <form method="POST" action="{{ route('varnish.vcl') }}">
                @csrf
                <textarea name="vcl" rows="12" class="w-full border rounded p-3 font-mono text-sm">{{ $vcl }}</textarea>
                <button class="mt-3 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">Save VCL</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Uninstall</h2>
        <p class="text-gray-600 text-sm mb-3">Remove Varnish Cache from this server. This will stop the service and remove the package.</p>
        <form method="POST" action="{{ route('varnish.uninstall') }}" onsubmit="return confirm('Are you sure you want to uninstall Varnish?')">
            @csrf
            <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">Uninstall Varnish</button>
        </form>
    </div>
    @endif
</div>
@endsection
