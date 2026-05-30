@extends('layouts.app')
@section('title', 'View Log')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700"><i class="fas fa-scroll mr-2 text-indigo-500"></i>{{ basename($path) }}</h3>
            <a href="{{ route('logs.index') }}" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-auto max-h-[600px] font-mono">{{ $content }}</pre>
    </div>
</div>
@endsection
