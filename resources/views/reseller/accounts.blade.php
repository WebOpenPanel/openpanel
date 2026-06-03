@extends('user-layouts.app')

@section('title', 'Reseller Accounts')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-800">Your Accounts</h2>
        <a href="{{ route('reseller.accounts.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">
            <i class="fas fa-plus mr-1"></i> Create Account
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Disk</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bandwidth</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($accounts as $account)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $account->domain }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $account->package->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ \App\Services\ShellService::formatBytes($account->disk_usage_bytes) }}
                            @if($account->disk_quota_bytes > 0)
                                / {{ \App\Services\ShellService::formatBytes($account->disk_quota_bytes) }}
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ \App\Services\ShellService::formatBytes($account->bandwidth_usage_bytes) }}
                            @if($account->bandwidth_limit_bytes > 0)
                                / {{ \App\Services\ShellService::formatBytes($account->bandwidth_limit_bytes) }}
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 text-xs {{ $account->isSuspended() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }} rounded-full">
                                {{ $account->isSuspended() ? 'Suspended' : 'Active' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection