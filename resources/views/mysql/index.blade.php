@extends('layouts.app')
@section('title', 'MySQL Manager')
@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Create Database -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-database mr-2 text-orange-500"></i>Create Database</h3>
            <form method="POST" action="{{ route('mysql.create-database') }}" class="space-y-3">
                @csrf
                <select name="user_account_id" required class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select Account</option>
                    @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->domain }} ({{ $a->user->username ?? '' }})</option>@endforeach
                </select>
                <input type="text" name="database_name" required placeholder="Database name" class="w-full px-3 py-2 border rounded-lg text-sm">
                <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg text-sm hover:bg-orange-700"><i class="fas fa-plus mr-1"></i> Create Database</button>
            </form>
        </div>
        <!-- Create User -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-user-shield mr-2 text-blue-500"></i>Create MySQL User</h3>
            <form method="POST" action="{{ route('mysql.create-user') }}" class="space-y-3">
                @csrf
                <select name="user_account_id" required class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">Select Account</option>
                    @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->domain }} ({{ $a->user->username ?? '' }})</option>@endforeach
                </select>
                <input type="text" name="username" required placeholder="Username" class="w-full px-3 py-2 border rounded-lg text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <input type="password" name="password" required placeholder="Password" class="px-3 py-2 border rounded-lg text-sm">
                    <input type="password" name="password_confirmation" required placeholder="Confirm" class="px-3 py-2 border rounded-lg text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"><i class="fas fa-plus mr-1"></i> Create User</button>
            </form>
        </div>
    </div>

    <!-- Databases List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b"><h3 class="text-sm font-semibold text-gray-700">Databases</h3></div>
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Database</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Account</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Charset</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($databases as $db)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm font-mono text-gray-800">{{ $db->database_name }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $db->userAccount->domain ?? '-' }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $db->charset }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('mysql.destroy-database', $db) }}" onsubmit="return confirm('Delete database?')" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No databases found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $databases->links() }}</div>
</div>
@endsection
