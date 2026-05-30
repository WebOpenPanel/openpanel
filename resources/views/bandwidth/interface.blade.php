@extends('layouts.app')
@section('title', 'Bandwidth - ' . $iface)
@section('content')
<div class="p-6">
    <div class="flex items-center gap-2 mb-6"><a href="{{ route('bandwidth.index') }}" class="text-blue-600">Bandwidth</a> <span>/</span><h1 class="text-2xl font-bold">{{ $iface }}</h1></div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Incoming</p><p class="text-2xl font-bold">{{ \App\Services\BandwidthService::formatBytes($usage['in_bytes']) }}</p></div>
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Outgoing</p><p class="text-2xl font-bold">{{ \App\Services\BandwidthService::formatBytes($usage['out_bytes']) }}</p></div>
        <div class="bg-white rounded-lg shadow p-6"><p class="text-sm text-gray-500">Total</p><p class="text-2xl font-bold text-blue-600">{{ \App\Services\BandwidthService::formatBytes($usage['total_bytes']) }}</p></div>
    </div>
</div>
@endsection
