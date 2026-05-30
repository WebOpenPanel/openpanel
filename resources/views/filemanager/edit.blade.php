@extends('layouts.app')
@section('title', 'Edit File')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700"><i class="fas fa-edit mr-2 text-indigo-500"></i>{{ basename($path) }}</h3>
            <a href="{{ route('files.index', ['path' => dirname($path)]) }}" class="text-sm text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
        <form method="POST" action="{{ route('files.save') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            <textarea name="content" rows="30" class="w-full px-3 py-2 border rounded-lg text-sm font-mono">{{ $content }}</textarea>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save File</button>
        </form>
    </div>
</div>
@endsection
