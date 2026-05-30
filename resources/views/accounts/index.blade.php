@extends('layouts.app')
@section('title', 'User Accounts')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search accounts..."
                    class="pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-64">
            </div>
            <select name="status" class="py-2 px-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="">All Status</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Filter</button>
        </form>
        <a href="{{ route('accounts.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> New Account
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Domain</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Username</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Package</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Disk Usage</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($accounts as $account)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('accounts.show', $account) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">{{ $account->domain }}</a>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700">{{ $account->user->username ?? '-' }}</td>
                        <td class="px-5 py-3 text-sm text-gray-600 font-mono">{{ $account->ip_address }}</td>
                        <td class="px-5 py-3 text-sm text-gray-600">{{ $account->package->name ?? '-' }}</td>
                        <td class="px-5 py-3">
                            <div class="w-24">
                                <div class="text-xs text-gray-500 mb-1">{{ $account->disk_usage_formatted }}</div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-indigo-500 h-1.5 rounded-full" @style(['width:'.$account->disk_usage_percent.'%'])></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            @if($account->isSuspended())
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Suspended</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('accounts.show', $account) }}" class="p-2 text-gray-400 hover:text-indigo-600 rounded-lg hover:bg-indigo-50" title="View">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <a href="{{ route('accounts.edit', $account) }}" class="p-2 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50" title="Edit">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                                @if($account->isSuspended())
                                    <form method="POST" action="{{ route('accounts.unsuspend', $account) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="p-2 text-gray-400 hover:text-green-600 rounded-lg hover:bg-green-50" title="Unsuspend">
                                            <i class="fas fa-play text-sm"></i>
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('accounts.suspend', $account) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="p-2 text-gray-400 hover:text-yellow-600 rounded-lg hover:bg-yellow-50" title="Suspend">
                                            <i class="fas fa-pause text-sm"></i>
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('Are you sure you want to delete this account?')" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50" title="Delete">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center">
                            <i class="fas fa-users text-gray-300 text-3xl mb-3"></i>
                            <p class="text-sm text-gray-500">No user accounts found.</p>
                            <a href="{{ route('accounts.create') }}" class="mt-2 inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800">
                                <i class="fas fa-plus mr-1"></i> Create your first account
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="">{{ $accounts->withQueryString()->links() }}</div>
</div>
@endsection
