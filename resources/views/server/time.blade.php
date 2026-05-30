@extends('layouts.app')
@section('title', 'Date & Time')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('server.hostname') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Hostname</a>
        <a href="{{ route('server.time') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Time</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-clock mr-2 text-indigo-500"></i>Server Time</h3>
        <div class="text-lg font-bold text-gray-800 mb-4">{{ $time['datetime'] ?? 'N/A' }} <span class="text-sm font-normal text-gray-500">({{ $time['timezone'] ?? '' }})</span></div>
        <form method="POST" action="{{ route('server.set-timezone') }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <select name="timezone" class="w-full px-3 py-2 border rounded-lg text-sm mb-3">
                @foreach($timezones as $tz)
                <option value="{{ $tz }}" {{ ($time['timezone'] ?? '') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Set Timezone</button>
        </form>
    </div>
</div>
@endsection
