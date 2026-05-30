@extends('layouts.app')
@section('title', 'Terminal')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('server.terminal') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Terminal</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-terminal mr-2 text-gray-700"></i>Shell Terminal</h3>
        <form method="POST" action="{{ route('server.run-command') }}" class="space-y-3">
            @csrf
            <div class="flex gap-2">
                <span class="px-3 py-2 bg-gray-800 text-green-400 rounded-l-lg text-sm font-mono">#</span>
                <input type="text" name="command" class="flex-1 px-3 py-2 border border-l-0 rounded-r-lg text-sm font-mono" placeholder="Enter command..." autofocus autocomplete="off">
                <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">Run</button>
            </div>
        </form>
        @if(session('output'))
        <div class="mt-4">
            <div class="text-xs text-gray-500 mb-1">Output:</div>
            <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-auto max-h-[500px] font-mono whitespace-pre-wrap">{{ session('output') }}</pre>
        </div>
        @endif
    </div>
</div>
@endsection
