@extends('layouts.app')
@section('title', $account->domain)

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('accounts.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-lg font-bold text-gray-900">{{ $account->domain }}</h2>
            @if($account->isSuspended())
                <span class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 rounded-full">Suspended</span>
            @else
                <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('accounts.edit', $account) }}" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200"><i class="fas fa-edit mr-1"></i> Edit</a>
            @if($account->isSuspended())
                <form method="POST" action="{{ route('accounts.unsuspend', $account) }}" class="inline">@csrf
                    <button class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700"><i class="fas fa-play mr-1"></i> Unsuspend</button>
                </form>
            @else
                <form method="POST" action="{{ route('accounts.suspend', $account) }}" class="inline">@csrf
                    <button class="px-3 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600"><i class="fas fa-pause mr-1"></i> Suspend</button>
                </form>
            @endif
        </div>
    </div>

    <!-- Account Info Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Username</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $account->user->username ?? '-' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">IP Address</p>
            <p class="mt-1 text-sm font-semibold font-mono text-gray-900">{{ $account->ip_address }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Package</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $account->package->name ?? '-' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Disk Usage</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $account->disk_usage_formatted }} / {{ $account->disk_quota_formatted }}</p>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                <div class="bg-indigo-500 h-1.5 rounded-full" @style(['width:'.$account->disk_usage_percent.'%'])></div>
            </div>
        </div>
    </div>

    <!-- Resource Counts -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ $account->domains->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Domains</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $account->dnsZones->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">DNS Zones</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-bold text-orange-600">{{ $account->mysqlDatabases->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Databases</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $account->emailAccounts->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Emails</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-bold text-purple-600">{{ $account->ftpAccounts->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">FTP</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
            <p class="text-2xl font-bold text-cyan-600">{{ $account->sslCertificates->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">SSL</p>
        </div>
    </div>

    <!-- Domains -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">Domains</h3>
        </div>
        <table class="w-full">
            <thead class="bg-gray-50"><tr>
                <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">SSL</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($account->domains as $domain)
                <tr><td class="px-5 py-2.5 text-sm text-gray-800">{{ $domain->domain }}</td>
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 text-xs bg-gray-100 rounded-full">{{ $domain->type }}</span></td>
                    <td class="px-5 py-2.5">@if($domain->ssl_enabled)<i class="fas fa-lock text-green-500"></i>@else<i class="fas fa-unlock text-gray-300"></i>@endif</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-5 py-4 text-center text-sm text-gray-400">No domains</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
