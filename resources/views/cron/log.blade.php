@extends('layouts.app')
@section('title', 'Cron Log')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('cron.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Cron Jobs</a>
        <a href="{{ route('cron.system') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">System Cron</a>
        <a href="{{ route('cron.log') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Log</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-scroll mr-2 text-indigo-500"></i>Cron Log</h3>
        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-auto max-h-[600px] font-mono">{{ $log }}</pre>
    </div>
</div>
@endsection
