@extends('layouts.app')
@section('title', 'Symlink Scan')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-link mr-2 text-indigo-500"></i>Symlink Scan</h3>
        <form method="GET" action="{{ route('security.symlink-scan') }}" class="flex gap-3 mb-4">
            <input type="text" name="path" value="{{ $path }}" placeholder="/home" class="flex-1 px-3 py-2 border rounded-lg text-sm">
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Scan</button>
        </form>
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-96 font-mono">{{ $results }}</pre>
    </div>
</div>
@endsection
