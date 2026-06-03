@extends('layouts.app')
@section('title', 'SysStat Report: ' . ucfirst($type))
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-chart-bar mr-2"></i>{{ ucfirst($type) }} Report</h1>
        <a href="{{ route('sysstat.index') }}" class="text-indigo-600 hover:underline">← Back</a>
    </div>
    <div class="flex gap-4 mb-4">
        @foreach(['cpu','memory','disk','network'] as $t)
            <a href="{{ route('sysstat.report', ['type' => $t]) }}" class="px-4 py-2 rounded-lg text-sm {{ $t === $type ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">{{ ucfirst($t) }}</a>
        @endforeach
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-auto text-sm font-mono" style="max-height:600px">{{ $output }}</pre>
    </div>
</div>
@endsection