@extends('layouts.app')
@section('title', 'ClamAV Antivirus')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">ClamAV Antivirus</h1>
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-6">ClamAV is not installed.</div>
    <form method="POST" action="{{ route('clamav.install') }}">@csrf<button class="bg-green-600 text-white px-6 py-2 rounded">Install ClamAV</button></form>
    @else
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Version</p><p class="font-mono text-sm mt-1">{{ $version }}</p></div>
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Quarantine</p><p class="text-2xl font-bold">{{ count($quarantine) }} files</p></div>
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Scan Logs</p><p class="text-2xl font-bold">{{ count($logs) }}</p></div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Scan</h2>
            <form method="POST" action="{{ route('clamav.scan-user') }}" class="mb-3">@csrf<div class="flex gap-2"><input type="text" name="user" placeholder="Username" class="border rounded p-2 flex-1"><button class="bg-blue-600 text-white px-4 py-2 rounded">Scan User</button></div></form>
            <form method="POST" action="{{ route('clamav.scan-path') }}" class="mb-3">@csrf<div class="flex gap-2"><input type="text" name="path" placeholder="/path/to/scan" class="border rounded p-2 flex-1"><button class="bg-blue-600 text-white px-4 py-2 rounded">Scan Path</button></div></form>
            <form method="POST" action="{{ route('clamav.scan-all') }}">@csrf<button class="bg-purple-600 text-white px-4 py-2 rounded w-full">Scan All Users</button></form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Virus Definitions</h2>
            <form method="POST" action="{{ route('clamav.update') }}">@csrf<button class="bg-green-600 text-white px-4 py-2 rounded">Update Definitions</button></form>
        </div>
    </div>
    @if(count($quarantine) > 0)
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Quarantine</h2>
        <table class="w-full text-sm"><thead><tr class="border-b"><th>File</th><th>Size</th><th>Modified</th><th></th></tr></thead>
        <tbody>@foreach($quarantine as $f)<tr class="border-b"><td class="font-mono text-xs">{{ $f['name'] }}</td><td>{{ number_format($f['size']/1024, 1) }} KB</td><td>{{ $f['modified'] }}</td><td class="text-right"><form method="POST" action="{{ route('clamav.delete') }}" class="inline">@csrf<input type="hidden" name="path" value="{{ $f['path'] }}"><button class="text-red-600 text-sm">Delete</button></form></td></tr>@endforeach</tbody></table>
    </div>
    @endif
    @endif
</div>
@endsection
