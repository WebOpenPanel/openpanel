@extends('layouts.app')
@section('title', $account['domain'] ?? 'Account')

@section('content')
@php $a = (object) $account; $suspended = ($a->status ?? 'active') === 'suspended'; @endphp
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('accounts.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
            <h2 class="text-lg font-bold text-gray-900">{{ $a->domain }}</h2>
            @if($suspended)
                <span class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 rounded-full">Suspended</span>
            @else
                <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('accounts.edit', $a->username) }}" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200"><i class="fas fa-edit mr-1"></i> Edit</a>
            @if($suspended)
                <form method="POST" action="{{ route('accounts.unsuspend', $a->username) }}" class="inline">@csrf
                    <button class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700"><i class="fas fa-play mr-1"></i> Unsuspend</button>
                </form>
            @else
                <form method="POST" action="{{ route('accounts.suspend', $a->username) }}" class="inline">@csrf
                    <button class="px-3 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600"><i class="fas fa-pause mr-1"></i> Suspend</button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Username</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $a->username }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">IP Address</p>
            <p class="mt-1 text-sm font-semibold font-mono text-gray-900">{{ $a->ip_address }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Package</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $a->package ?? '-' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Disk Limit</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $a->disk_limit ?? 0 }} MB</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Email</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $a->email ?? '-' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Home Directory</p>
            <p class="mt-1 text-sm font-mono text-gray-900">{{ $home ?? '/home/' . $a->username }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Disk Used</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $diskUsed ?? 0 }} MB</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase">Bandwidth Limit</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $a->bandwidth_limit ?? 0 }} MB</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">Account Details</h3>
        </div>
        <div class="p-5 space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Domain</span><span class="font-medium">{{ $a->domain }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Username</span><span class="font-medium font-mono">{{ $a->username }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">IP Address</span><span class="font-medium font-mono">{{ $a->ip_address }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Status</span><span class="font-medium">{{ ucfirst($a->status ?? 'active') }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Created</span><span class="font-medium">{{ $a->created_at ?? '-' }}</span></div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Quick Actions</h3>
        </div>
        <div class="p-5 flex flex-wrap gap-3">
            <a href="{{ route('accounts.edit', $a->username) }}" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200"><i class="fas fa-key mr-1"></i> Change Password</a>
            <form method="POST" action="{{ route('accounts.destroy', $a->username) }}" onsubmit="return confirm('Delete account {{ $a->username }}? This is irreversible!')" class="inline">@csrf @method('DELETE')
                <button class="px-4 py-2 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200"><i class="fas fa-trash mr-1"></i> Delete Account</button>
            </form>
        </div>
    </div>
</div>
@endsection
