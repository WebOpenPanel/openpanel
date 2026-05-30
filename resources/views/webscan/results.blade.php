@extends('layouts.app')
@section('title', 'Web Scan Results')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Scan Results: {{ $results['domain'] }}</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Score</p><p class="text-4xl font-bold {{ $results['score'] >= 80 ? 'text-green-600' : ($results['score'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">{{ $results['score'] }}/100</p></div>
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Issues Found</p><p class="text-4xl font-bold text-red-600">{{ count($results['issues']) }}</p></div>
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Scanned</p><p class="text-sm mt-2">{{ $results['scanned_at'] }}</p></div>
    </div>
    @forelse($results['issues'] as $issue)
    <div class="bg-white rounded-lg shadow p-4 mb-3 border-l-4 {{ match($issue['severity'] ?? 'info') { 'critical' => 'border-red-800', 'high' => 'border-red-500', 'medium' => 'border-yellow-500', 'warning' => 'border-orange-500', default => 'border-blue-500' } }}">
        <div class="flex justify-between"><h3 class="font-semibold">{{ $issue['title'] }}</h3><span class="px-2 py-1 rounded text-xs {{ match($issue['severity'] ?? 'info') { 'critical','high' => 'bg-red-100 text-red-800', 'medium','warning' => 'bg-yellow-100 text-yellow-800', default => 'bg-blue-100 text-blue-800' } }}">{{ $issue['severity'] ?? 'info' }}</span></div>
        <p class="text-sm text-gray-600 mt-1">{{ $issue['description'] ?? '' }}</p>
        @if(!empty($issue['files']))<div class="mt-2">@foreach($issue['files'] as $f)<p class="text-xs font-mono text-gray-400">{{ $f }}</p>@endforeach</div>@endif
    </div>
    @empty
    <div class="bg-green-50 border border-green-200 rounded p-6 text-center"><p class="text-green-800 font-semibold">No issues found. Score: {{ $results['score'] }}/100</p></div>
    @endforelse
    <a href="{{ route('webscan.index') }}" class="inline-block mt-4 text-blue-600">Back to Web Scan</a>
</div>
@endsection
