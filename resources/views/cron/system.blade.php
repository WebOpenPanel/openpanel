@extends('layouts.app')
@section('title', 'System Cron')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('cron.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Cron Jobs</a>
        <a href="{{ route('cron.system') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">System Cron</a>
        <a href="{{ route('cron.log') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Log</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700"><i class="fas fa-clock mr-2 text-indigo-500"></i>Root Crontab</h3>
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ ($daemon['active'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">crond: {{ ($daemon['active'] ?? false) ? 'Active' : 'Inactive' }}</span>
        </div>
        <form method="POST" action="{{ route('cron.save-system') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="username" value="root">
            <textarea name="content" rows="15" class="w-full px-3 py-2 border rounded-lg text-sm font-mono">@foreach($jobs as $job){{ $job['raw'] ?? '' }}
@endforeach</textarea>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save Crontab</button>
        </form>
    </div>
    @if(!empty($allCrontabs))
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">All User Crontabs</h3>
        @foreach($allCrontabs as $ct)
        <details class="border rounded-lg mb-2">
            <summary class="px-4 py-2 text-sm font-medium cursor-pointer hover:bg-gray-50">{{ $ct['username'] }} <span class="text-gray-400 font-normal ml-2">{{ $ct['modified'] }}</span></summary>
            <pre class="px-4 py-3 text-xs bg-gray-50 font-mono">{{ $ct['content'] }}</pre>
        </details>
        @endforeach
    </div>
    @endif
</div>
@endsection
