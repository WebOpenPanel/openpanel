@extends('layouts.app')
@section('title', 'View File')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700"><i class="fas fa-file mr-2 text-indigo-500"></i>{{ basename($path) }}</h3>
            <div class="flex gap-2">
                <a href="{{ route('files.edit', ['path' => $path]) }}" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700">Edit</a>
                <a href="{{ route('files.index', ['path' => dirname($path)]) }}" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i>Back</a>
            </div>
        </div>
        <pre class="bg-gray-50 p-4 rounded-lg text-xs overflow-auto max-h-[600px] font-mono whitespace-pre-wrap">{{ $content }}</pre>
    </div>
</div>
@endsection
