@extends('layouts.app')
@section('title', 'MySQL Status')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('mysql.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Databases</a>
        <a href="{{ route('mysql.status') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Status</a>
        <a href="{{ route('mysql.processes') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Processes</a>
        <a href="{{ route('mysql.config') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Config</a>
        <a href="{{ route('mysql.postgresql') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">PostgreSQL</a>
        <a href="{{ route('mysql.mongodb') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">MongoDB</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-database mr-2 text-indigo-500"></i>MySQL Status</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            @php $keys = ['Uptime','Threads_connected','Threads_running','Queries','Slow_queries','Bytes_received','Bytes_sent','Innodb_buffer_pool_reads']; @endphp
            @foreach($keys as $key)
            <div class="bg-gray-50 p-3 rounded-lg">
                <div class="text-xs text-gray-500">{{ $key }}</div>
                <div class="text-lg font-bold text-gray-800">{{ $status['status'][$key] ?? 'N/A' }}</div>
            </div>
            @endforeach
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Service Status</h3>
        <span class="px-3 py-1 rounded-full text-sm font-medium {{ ($serviceStatus['active'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
            {{ ($serviceStatus['active'] ?? false) ? 'Running' : 'Stopped' }}
        </span>
    </div>
    @if(!empty($status['variables']))
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <details>
            <summary class="px-4 py-3 text-sm font-semibold text-gray-700 cursor-pointer hover:bg-gray-50">All Variables</summary>
            <div class="p-4 max-h-96 overflow-auto"><table class="w-full text-xs">
                <thead class="border-b"><tr><th class="text-left py-1 pr-4">Variable</th><th class="text-left py-1">Value</th></tr></thead>
                <tbody class="divide-y">
                @foreach($status['variables'] as $k => $v)
                <tr><td class="py-1 pr-4 font-medium">{{ $k }}</td><td class="py-1 text-gray-600">{{ $v }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        </details>
    </div>
    @endif
</div>
@endsection
