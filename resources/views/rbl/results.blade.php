@extends('layouts.app')
@section('title', 'RBL Check Results')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">RBL Check Results: {{ $results['ip'] }}</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Status</p><p class="text-2xl font-bold {{ $results['clean'] ? 'text-green-600' : 'text-red-600' }}">{{ $results['clean'] ? 'CLEAN' : 'LISTED' }}</p></div>
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Listed On</p><p class="text-2xl font-bold text-red-600">{{ $results['listed_count'] }}</p></div>
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Checked</p><p class="text-2xl font-bold">{{ $results['total_checked'] }}</p></div>
    </div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm"><thead><tr class="bg-gray-50 border-b"><th class="text-left px-4 py-3">Blacklist</th><th class="text-center px-4 py-3">Status</th><th class="text-left px-4 py-3">Response</th></tr></thead>
        <tbody>@foreach($results['results'] as $r)<tr class="border-b"><td class="px-4 py-2 font-mono text-xs">{{ $r['blacklist'] }}</td><td class="px-4 py-2 text-center"><span class="px-2 py-1 rounded text-xs {{ $r['listed'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">{{ $r['listed'] ? 'LISTED' : 'OK' }}</span></td><td class="px-4 py-2 text-xs font-mono">{{ $r['response'] ?: '-' }}</td></tr>@endforeach</tbody></table>
    </div>
    <a href="{{ route('rbl.index') }}" class="inline-block mt-4 text-blue-600">Back to RBL Check</a>
</div>
@endsection
