@extends('layouts.app')
@section('title', 'MongoDB Manager')
@section('content')
<div class="space-y-6">
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800">MongoDB is not installed.</p>
        <form method="POST" action="{{ route('mongo.install') }}" class="mt-2">@csrf
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Install MongoDB</button>
        </form>
    </div>
    @else
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">MongoDB</h2>
        <span class="text-sm text-gray-500">{{ $version }}</span>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Databases</h3>
        <form method="POST" action="{{ route('mongo.create-db') }}" class="flex gap-2 mb-4">
            @csrf
            <input name="name" placeholder="Database name" class="border rounded px-3 py-1 flex-1" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Create</button>
        </form>
        @foreach($databases as $db)
        <span class="inline-block bg-gray-100 rounded px-2 py-1 text-sm mr-1 mb-1">{{ $db }}</span>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Create User</h3>
        <form method="POST" action="{{ route('mongo.create-user') }}" class="space-y-2">
            @csrf
            <input name="username" placeholder="Username" class="border rounded px-3 py-1 w-full" required>
            <input name="password" type="password" placeholder="Password" class="border rounded px-3 py-1 w-full" required>
            <input name="database" placeholder="Database" class="border rounded px-3 py-1 w-full" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Create User</button>
        </form>
    </div>
    @endif
</div>
@endsection
