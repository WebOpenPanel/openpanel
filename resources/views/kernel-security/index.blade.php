@extends('layouts.app')
@section('title', 'Kernel Security')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Kernel Security</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6 text-center"><p class="text-sm text-gray-500">Security Score</p><p class="text-4xl font-bold {{ $score['score'] >= 80 ? 'text-green-600' : ($score['score'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">{{ $score['score'] }}/100</p></div>
        <div class="bg-white rounded-lg shadow p-6 text-center"><p class="text-sm text-gray-500">Issues Found</p><p class="text-4xl font-bold text-red-600">{{ count($score['issues']) }}</p></div>
        <div class="bg-white rounded-lg shadow p-6 text-center"><p class="text-sm text-gray-500">Blacklisted Modules</p><p class="text-4xl font-bold">{{ count($blacklisted) }}</p></div>
    </div>
    @if(count($score['issues']) > 0)
    <div class="bg-red-50 border border-red-200 rounded p-4 mb-6">
        <h3 class="font-semibold text-red-800 mb-2">Issues</h3>
        @foreach($score['issues'] as $issue)<p class="text-sm text-red-700">{{ $issue }}</p>@endforeach
        <form method="POST" action="{{ route('kernel-security.harden') }}" class="mt-3">@csrf<button class="bg-red-600 text-white px-4 py-2 rounded">Apply Kernel Hardening</button></form>
    </div>
    @endif
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Blacklisted Modules</h2>
            @foreach($blacklisted as $mod)<div class="flex justify-between py-1 border-b"><span class="font-mono text-sm">{{ $mod }}</span><form method="POST" action="{{ route('kernel-security.unblacklist') }}">@csrf<input type="hidden" name="module" value="{{ $mod }}"><button class="text-red-600 text-sm">Remove</button></form></div>@endforeach
            <form method="POST" action="{{ route('kernel-security.blacklist') }}" class="mt-3 flex gap-2">@csrf<input type="text" name="module" placeholder="module_name" class="border rounded p-2 flex-1"><button class="bg-blue-600 text-white px-3 py-2 rounded">Blacklist</button></form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Loaded Modules ({{ count($modules) }})</h2>
            <div class="max-h-64 overflow-y-auto"><div class="flex flex-wrap gap-1">@foreach($modules as $mod)<span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ $mod }}</span>@endforeach</div></div>
        </div>
    </div>
</div>
@endsection
