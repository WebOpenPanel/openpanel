@extends('layouts.app')
@section('title', 'Create Template')
@section('content')
<div class="p-6">
    <div class="flex items-center gap-2 mb-6"><a href="{{ route('webserver-templates.index') }}" class="text-blue-600">Templates</a> <span>/</span><h1 class="text-2xl font-bold">Create Template</h1></div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">New Template</h2>
            <form method="POST" action="{{ route('webserver-templates.save') }}">@csrf
                <label class="block text-sm font-medium mb-1">Template Name</label>
                <input type="text" name="name" class="w-full border rounded p-2 mb-3" required>
                <label class="block text-sm font-medium mb-1">Content</label>
                <textarea name="content" rows="20" class="w-full border rounded p-3 font-mono text-sm"></textarea>
                <button class="mt-3 bg-blue-600 text-white px-6 py-2 rounded">Create Template</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Generate from Type</h2>
            <form method="POST" action="{{ route('webserver-templates.generate') }}">@csrf
                <label class="block text-sm font-medium mb-1">Server Type</label>
                <select name="type" class="w-full border rounded p-2 mb-3">@foreach($types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                <label class="block text-sm font-medium mb-1">Domain</label>
                <input type="text" name="domain" class="w-full border rounded p-2 mb-3" required>
                <label class="block text-sm font-medium mb-1">Username</label>
                <input type="text" name="user" class="w-full border rounded p-2 mb-3" required>
                <button class="bg-green-600 text-white px-6 py-2 rounded">Generate</button>
            </form>
        </div>
    </div>
</div>
@endsection
