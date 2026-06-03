@extends('layouts.app')
@section('title', 'PostgreSQL Manager')
@section('content')
<div class="space-y-6">
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800">PostgreSQL is not installed.</p>
        <form method="POST" action="{{ route('postgresql.install') }}" class="mt-2">
            @csrf
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Install PostgreSQL</button>
        </form>
    </div>
    @else
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">PostgreSQL {{ $version }}</h2>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('postgresql.service') }}">
                @csrf
                <input type="hidden" name="action" value="restart">
                <button class="bg-green-600 text-white px-3 py-1 rounded text-sm">Restart</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Databases</h3>
        <form method="POST" action="{{ route('postgresql.create-db') }}" class="flex gap-2 mb-4">
            @csrf
            <input name="name" placeholder="Database name" class="border rounded px-3 py-1 flex-1" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Create</button>
        </form>
        <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="text-left py-2">Database</th><th class="text-right">Action</th></tr></thead>
            <tbody>
            @foreach($databases as $db)
            <tr class="border-b"><td class="py-2">{{ $db }}</td>
                <td class="text-right">
                    <form method="POST" action="{{ route('postgresql.drop-db') }}" class="inline">
                        @csrf
                        <input type="hidden" name="name" value="{{ $db }}">
                        <button class="text-red-600 text-sm" onclick="return confirm('Drop {{ $db }}?')">Drop</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Users</h3>
        <form method="POST" action="{{ route('postgresql.create-user') }}" class="flex gap-2 mb-4">
            @csrf
            <input name="username" placeholder="Username" class="border rounded px-3 py-1" required>
            <input name="password" type="password" placeholder="Password" class="border rounded px-3 py-1" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Create</button>
        </form>
        @foreach($users as $u)
        <span class="inline-block bg-gray-100 rounded px-2 py-1 text-sm mr-1 mb-1">{{ $u }}</span>
        @endforeach
    </div>
    @endif
</div>
@endsection
