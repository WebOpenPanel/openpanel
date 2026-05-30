@extends('layouts.app')
@section('title', 'Log Viewer')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-scroll mr-2 text-indigo-500"></i>Log Files <span class="text-gray-400 font-normal">({{ $logDir }})</span></h3>
        <form method="GET" class="flex gap-2 mb-3">
            <input type="text" name="dir" value="{{ $logDir }}" placeholder="/var/log" class="flex-1 px-3 py-2 border rounded-lg text-sm">
            <button class="px-3 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700">Browse</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">File</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Size</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Modified</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($files as $f)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-mono">{{ $f['name'] }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ number_format($f['size'] / 1024, 1) }} KB</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ $f['modified'] }}</td>
                    <td class="px-4 py-2"><a href="{{ route('logs.view', ['path' => $f['path']]) }}" class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs hover:bg-indigo-200">View</a></td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No log files</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
