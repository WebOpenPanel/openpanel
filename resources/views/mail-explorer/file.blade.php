@extends('layouts.app')
@section('title', 'View File')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold text-gray-800">{{ basename($path) }}</h1>
        <button onclick="history.back()" class="text-indigo-600 hover:underline">← Back</button>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-sm text-gray-500 mb-4">Path: {{ $path }}</p>
        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-auto text-sm font-mono" style="max-height:600px">{{ $content }}</pre>
    </div>
</div>
@endsection