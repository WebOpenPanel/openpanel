@extends('layouts.app')
@section('title', 'Kernel')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.kernel') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Kernel</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-microchip mr-2 text-indigo-500"></i>Kernel Information</h3>
        <div class="space-y-2">
            <div class="text-sm"><span class="font-medium text-gray-700">Version:</span> <span class="font-mono text-gray-600">{{ $info['version'] ?? 'N/A' }}</span></div>
            <div class="text-sm"><span class="font-medium text-gray-700">Full:</span> <span class="font-mono text-gray-600 text-xs">{{ $info['full'] ?? 'N/A' }}</span></div>
        </div>
        <form method="POST" action="{{ route('security.kernel-update') }}" class="mt-4">
            @csrf
            <button class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700" onclick="return confirm('Update kernel? Server reboot required.')">Update Kernel</button>
        </form>
    </div>
    @if(session('output'))
    <div class="bg-white rounded-xl shadow-sm border p-5"><pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ session('output') }}</pre></div>
    @endif
</div>
@endsection
