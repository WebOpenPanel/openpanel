@extends('layouts.app')
@section('title', 'Edit: ' . $file)
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Editing: {{ $file }}</h1>
        <a href="{{ route('config-editor.index') }}" class="text-indigo-600 hover:underline">← Back</a>
    </div>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>@endif
    <form action="{{ route('config-editor.save') }}" method="POST" class="bg-white rounded-lg shadow p-6">
        @csrf
        <input type="hidden" name="file" value="{{ $file }}">
        <textarea name="content" rows="30" class="w-full font-mono text-sm border rounded-lg p-4">{{ $content }}</textarea>
        <div class="mt-4 flex gap-4">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Save</button>
        </div>
    </form>
</div>
@endsection
