@extends('user-layouts.app')

@section('title', 'Reseller Dashboard')

@section('content')
<div class="space-y-6">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <h2 class="text-xl font-bold">Reseller Dashboard</h2>
        <p class="text-indigo-100 mt-1">Manage your hosting accounts and clients.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Accounts</p>
                    <p class="text-xl font-bold text-gray-800">{{ $totalAccounts }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-hdd text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Disk Used</p>
                    <p class="text-xl font-bold text-gray-800">{{ \App\Services\ShellService::formatBytes($totalDiskUsed) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-database text-yellow-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Disk Quota</p>
                    <p class="text-xl font-bold text-gray-800">{{ $totalDiskQuota > 0 ? \App\Services\ShellService::formatBytes($totalDiskQuota) : 'Unlimited' }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 {{ $suspendedAccounts > 0 ? 'bg-red-100' : 'bg-gray-100' }} rounded-lg flex items-center justify-center">
                    <i class="fas fa-ban {{ $suspendedAccounts > 0 ? 'text-red-600' : 'text-gray-400' }}"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Suspended</p>
                    <p class="text-xl font-bold text-gray-800">{{ $suspendedAccounts }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Your Accounts</h3>
            <a href="{{ route('reseller.accounts.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">
                <i class="fas fa-plus mr-1"></i> Create Account
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Disk</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($managedUsers as $account)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $account->domain }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $account->package->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ \App\Services\ShellService::formatBytes($account->disk_usage_bytes) }}
                            @if($account->disk_quota_bytes > 0)
                                / {{ \App\Services\ShellService::formatBytes($account->disk_quota_bytes) }}
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 text-xs {{ $account->isSuspended() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }} rounded-full">
                                {{ $account->isSuspended() ? 'Suspended' : 'Active' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection