@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Servercast</h1>
    @if(session('output'))<pre class="bg-gray-900 text-green-400 p-4 rounded mb-4 text-sm">{{ session('output') }}</pre>@endif
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="POST" action="{{ route('servercast.store') }}" class="grid grid-cols-2 gap-4">@csrf
            <input type="text" name="command" placeholder="Command" class="border rounded p-2" required>
            <input type="text" name="description" placeholder="Description" class="border rounded p-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Add Cast</button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        @foreach($casts as $cast)<div class="flex items-center justify-between border-b py-2">
            <span>{{ $cast['command'] ?? '' }} <small class="text-gray-500">{{ $cast['description'] ?? '' }}</small></span>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('servercast.execute', $cast['file']) }}">@csrf<button class="bg-green-600 text-white px-2 py-1 rounded text-sm">Run</button></form>
                <form method="POST" action="{{ route('servercast.destroy', $cast['file']) }}">@csrf @method('DELETE')<button class="text-red-600 text-sm">Delete</button></form>
            </div>
        </div>@endforeach
    </div>
</div>
@endsection
