@extends('layouts.app')
@section('title', 'File Search')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-search mr-2 text-indigo-500"></i>Search Results</h3>
        <div class="space-y-2">
            @forelse($results as $r)
            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                <div>
                    <div class="text-sm font-medium text-gray-800">{{ $r['name'] }}</div>
                    <div class="text-xs text-gray-500 font-mono">{{ $r['path'] }}</div>
                </div>
                <div class="text-xs text-gray-400">{{ number_format($r['size'] / 1024, 1) }} KB</div>
            </div>
            @empty
            <div class="text-center py-8 text-gray-400">No files found</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
