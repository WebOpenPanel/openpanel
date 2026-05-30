@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">CloudLinux Manager</h1>
    @if(!$installed)<div class="bg-yellow-50 border border-yellow-200 rounded p-4">CloudLinux is not installed on this server.</div>@else
    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">CageFS</h2>
            <p class="mb-2"><strong>Status:</strong> {{ $cageStatus }}</p>
            <p class="mb-4"><strong>Mode:</strong> {{ $cageMode }}</p>
            <div class="flex gap-2 flex-wrap">
                <form method="POST" action="{{ route('cloudlinux.cagefs-enable') }}">@csrf<button class="bg-green-600 text-white px-3 py-1 rounded text-sm">Enable</button></form>
                <form method="POST" action="{{ route('cloudlinux.cagefs-disable') }}">@csrf<button class="bg-red-600 text-white px-3 py-1 rounded text-sm">Disable</button></form>
                <form method="POST" action="{{ route('cloudlinux.cagefs-update') }}">@csrf<button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Update</button></form>
                <form method="POST" action="{{ route('cloudlinux.cagefs-enable-all') }}">@csrf<button class="bg-green-500 text-white px-3 py-1 rounded text-sm">Enable All</button></form>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">LVE Limits</h2>
            <pre class="text-sm bg-gray-50 p-4 rounded max-h-64 overflow-auto">{{ $lveLimits }}</pre>
        </div>
        <div class="bg-white rounded-lg shadow p-6 col-span-2">
            <h2 class="text-lg font-semibold mb-4">PHP Selector</h2>
            <pre class="text-sm bg-gray-50 p-4 rounded max-h-64 overflow-auto">{{ $phpVersions }}</pre>
        </div>
    </div>@endif
</div>
@endsection
