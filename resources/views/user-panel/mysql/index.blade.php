@extends('user-layouts.app')

@section('title', 'MySQL Manager')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Databases</h3>
            </div>
            <div class="p-6 space-y-3">
                @forelse($databases as $db)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-800">{{ $db->name }}</span>
                        <form method="POST" action="{{ route('user.mysql.database.delete') }}" onsubmit="return confirm('Delete database {{ $db->name }}?')">
                            @csrf
                            <input type="hidden" name="id" value="{{ $db->id }}">
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">No databases found.</p>
                @endforelse
            </div>
            <div class="p-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Create Database</h4>
                <form method="POST" action="{{ route('user.mysql.database.create') }}" class="flex gap-3">
                    @csrf
                    <span class="text-sm text-gray-500 py-2">{{ auth()->user()->username }}_</span>
                    <input type="text" name="name" placeholder="dbname" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm" required pattern="[a-zA-Z0-9_]+">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">Create</button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Database Users</h3>
            </div>
            <div class="p-6 space-y-3">
                @forelse($users as $u)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-800">{{ $u->username }}</span>
                        <div class="space-x-2">
                            <form method="POST" action="{{ route('user.mysql.user.delete') }}" class="inline" onsubmit="return confirm('Delete user {{ $u->username }}?')">
                                @csrf
                                <input type="hidden" name="id" value="{{ $u->id }}">
                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">No database users found.</p>
                @endforelse
            </div>
            <div class="p-6 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Create User</h4>
                <form method="POST" action="{{ route('user.mysql.user.create') }}" class="space-y-3">
                    @csrf
                    <div class="flex gap-3">
                        <span class="text-sm text-gray-500 py-2">{{ auth()->user()->username }}_</span>
                        <input type="text" name="username" placeholder="dbuser" class="flex-1 px-3 py-2 border rounded-lg text-sm" required pattern="[a-zA-Z0-9_]+">
                    </div>
                    <input type="password" name="password" placeholder="Password" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">Create User</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
