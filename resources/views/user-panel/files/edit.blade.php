@extends('user-layouts.app')

@section('title', 'Edit File')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-file-code mr-2 text-blue-600"></i>{{ basename($path) }}</h3>
        <a href="{{ route('user.files.index', ['path' => dirname($path) === '.' ? '' : dirname($path)]) }}" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>
    <form method="POST" action="{{ route('user.files.save') }}">
        @csrf
        <input type="hidden" name="path" value="{{ $path }}">
        <textarea name="content" rows="30" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm">{{ $content }}</textarea>
        <div class="mt-4">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"><i class="fas fa-save mr-2"></i>Save</button>
        </div>
    </form>
</div>
@endsection
