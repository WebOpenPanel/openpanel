@extends('user-layouts.app')

@section('title', 'FTP Accounts')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">FTP Accounts</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Path</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($accounts as $account)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $account->ftp_user }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $account->path }}</td>
                            <td class="px-6 py-4 space-x-3">
                                <form method="POST" action="{{ route('user.ftp.delete') }}" class="inline" onsubmit="return confirm('Delete this FTP account?')">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $account->id }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash mr-1"></i>Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">No FTP accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Create FTP Account</h3>
        <form method="POST" action="{{ route('user.ftp.create') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @csrf
            <input type="text" name="ftp_user" placeholder="FTP Username" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
            <input type="password" name="password" placeholder="Password" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
            <input type="text" name="path" placeholder="Directory (e.g. public_html)" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Create FTP Account</button>
        </form>
    </div>
</div>
@endsection
