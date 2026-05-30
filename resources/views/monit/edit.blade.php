@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Edit Config: {{ $file }}</h1>
    <form method="POST" action="{{ route('monit.config-save') }}" class="bg-white rounded-lg shadow p-6">@csrf @method('PUT')
        <input type="hidden" name="file" value="{{ $file }}">
        <textarea name="content" rows="20" class="w-full border rounded p-2 font-mono text-sm">{{ $content }}</textarea>
        <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">Save</button>
    </form>
</div>
@endsection