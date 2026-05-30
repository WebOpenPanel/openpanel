@extends('layouts.app')
@section('title', 'Edit Template')
@section('content')
<div class="p-6">
    <div class="flex items-center gap-2 mb-6"><a href="{{ route('webserver-templates.index') }}" class="text-blue-600">Templates</a> <span>/</span><h1 class="text-2xl font-bold">Edit: {{ $name }}</h1></div>
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('webserver-templates.save') }}">@csrf
            <input type="hidden" name="name" value="{{ $name }}">
            <textarea name="content" rows="30" class="w-full border rounded p-3 font-mono text-sm">{{ $content }}</textarea>
            <div class="flex gap-2 mt-4"><button class="bg-blue-600 text-white px-6 py-2 rounded">Save Template</button><a href="{{ route('webserver-templates.index') }}" class="bg-gray-300 px-6 py-2 rounded">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
