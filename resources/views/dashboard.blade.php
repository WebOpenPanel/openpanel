@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">User Accounts</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['total_accounts'] }}</p>
                    <p class="text-xs text-green-600 mt-1">{{ $stats['active_accounts'] }} active</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Domains</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['total_domains'] }}</p>
                    <p class="text-xs text-green-600 mt-1">{{ $stats['ssl_domains'] }} with SSL</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-globe text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Databases</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['databases'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">MySQL databases</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-database text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Email Accounts</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['email_accounts'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Active mailboxes</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-envelope text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- System Info Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- CPU Load -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-microchip mr-2 text-indigo-500"></i> CPU Load
            </h3>
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">1 min</span>
                        <span class="font-medium">{{ number_format($cpuLoad['1min'], 2) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-indigo-500 h-2 rounded-full transition-all" @style(['width:'.min($cpuLoad['1min']*10,100).'%'])></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">5 min</span>
                        <span class="font-medium">{{ number_format($cpuLoad['5min'], 2) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full transition-all" @style(['width:'.min($cpuLoad['5min']*10,100).'%'])></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">15 min</span>
                        <span class="font-medium">{{ number_format($cpuLoad['15min'], 2) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-cyan-500 h-2 rounded-full transition-all" @style(['width:'.min($cpuLoad['15min']*10,100).'%'])></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disk Usage -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-hdd mr-2 text-orange-500"></i> Disk Usage
            </h3>
            <div class="flex items-center justify-center mb-4">
                <div class="relative w-28 h-28">
                    <svg class="w-28 h-28 transform -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                        <circle cx="50" cy="50" r="40" fill="none" stroke="{{ $diskUsage['percent'] > 80 ? '#ef4444' : ($diskUsage['percent'] > 60 ? '#f59e0b' : '#6366f1') }}" stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="{{ $diskUsage['percent'] * 2.51 }} 251.2"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-lg font-bold text-gray-800">{{ $diskUsage['percent'] }}%</span>
                    </div>
                </div>
            </div>
            <div class="text-center text-sm text-gray-500">
                {{ round($diskUsage['used'] / (1024**3), 1) }} GB / {{ round($diskUsage['total'] / (1024**3), 1) }} GB used
            </div>
        </div>

        <!-- SSL Summary -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-lock mr-2 text-green-500"></i> SSL Summary
            </h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        <span class="text-sm text-gray-700">Active Certificates</span>
                    </div>
                    <span class="text-sm font-bold text-green-700">{{ $stats['ssl_domains'] }}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                        <span class="text-sm text-gray-700">Expiring Soon</span>
                    </div>
                    <span class="text-sm font-bold text-yellow-700">{{ $stats['expiring_ssl'] }}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-times-circle text-red-500 mr-2"></i>
                        <span class="text-sm text-gray-700">Expired</span>
                    </div>
                    <span class="text-sm font-bold text-red-700">{{ $stats['expired_ssl'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Services & Notifications -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Services Status -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 flex items-center">
                    <i class="fas fa-cogs mr-2 text-indigo-500"></i> Services Status
                </h3>
                <span class="text-xs text-gray-500">{{ $stats['running_services'] }}/{{ $stats['total_services'] }} running</span>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <table class="w-full">
                    <tbody class="divide-y divide-gray-100">
                        @forelse($services as $service)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <span class="text-sm font-medium text-gray-800">{{ $service->display_name }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if($service->status === 'running')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-circle text-green-400 mr-1 text-[5px]"></i> Running
                                    </span>
                                @elseif($service->status === 'stopped')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-circle text-red-400 mr-1 text-[5px]"></i> Stopped
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Unknown
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td class="px-5 py-8 text-center text-sm text-gray-500">No services configured yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notifications -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 flex items-center">
                    <i class="fas fa-bell mr-2 text-yellow-500"></i> Recent Notifications
                </h3>
            </div>
            <div class="max-h-80 overflow-y-auto">
                @forelse($recentNotifications as $notification)
                <div class="px-5 py-3 border-b border-gray-50 hover:bg-gray-50">
                    <div class="flex items-start">
                        @if($notification->type === 'danger')
                            <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                        @elseif($notification->type === 'warning')
                            <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5 mr-3"></i>
                        @elseif($notification->type === 'success')
                            <i class="fas fa-check-circle text-green-500 mt-0.5 mr-3"></i>
                        @else
                            <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800">{{ $notification->title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
                @empty
                <div class="px-5 py-8 text-center text-sm text-gray-500">
                    <i class="fas fa-bell-slash text-gray-300 text-2xl mb-2"></i>
                    <p>No new notifications</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
