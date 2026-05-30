@extends('layouts.app')
@section('title', 'IP Details')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700"><i class="fas fa-network-wired mr-2 text-indigo-500"></i>IP Details: {{ $details['ip'] }}</h3>
            <a href="{{ route('ip.index') }}" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <div class="text-sm"><span class="font-medium text-gray-700">Domains Using:</span> {{ $details['domains_using'] }}</div>
                <div class="text-sm"><span class="font-medium text-gray-700">Reverse DNS:</span> <span class="font-mono text-gray-600">{{ $details['reverse_dns'] ?? 'N/A' }}</span></div>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">Configuration</h4>
                <pre class="bg-gray-50 p-3 rounded text-xs font-mono">{{ json_encode($details['info'], JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>
</div>
@endsection
