@extends('layouts.app')
@section('title', 'Service Configuration')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700"><i class="fas fa-cog mr-2 text-indigo-500"></i>{{ $service->display_name }} Configuration</h3>
            <a href="{{ route('services.index') }}" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
        @if($configFile)
        <div class="text-xs text-gray-500 mb-2 font-mono">{{ $configFile }}</div>
        @endif
        @if($content)
        <form method="POST" action="{{ route('services.save-config', $service) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="path" value="{{ $configFile }}">
            <textarea name="content" rows="30" class="w-full px-3 py-2 border rounded-lg text-sm font-mono">{{ $content }}</textarea>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save Configuration</button>
        </form>
        @else
        <div class="text-center py-8 text-gray-400"><i class="fas fa-info-circle text-3xl mb-2"></i><p>No configuration file found for this service.</p></div>
        @endif
    </div>
</div>
@endsection
