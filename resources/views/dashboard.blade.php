@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Accounts</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $accounts['total'] }}</p>
                    <p class="text-xs text-green-600 mt-1">{{ $accounts['active'] }} active</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Active</p>
                    <p class="mt-1 text-2xl font-bold text-green-700">{{ $accounts['active'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Suspended</p>
                    <p class="mt-1 text-2xl font-bold text-red-700">{{ $accounts['suspended'] }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-ban text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-microchip mr-2 text-indigo-500"></i> CPU
            </h3>
            <div class="space-y-3">
                @foreach(['1min', '5min', '15min'] as $period)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">{{ $period }}</span>
                        <span class="font-medium">{{ number_format($system['cpu'][$period], 2) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ min($system['cpu'][$period] * 10, 100) }}%"></div>
                    </div>
                </div>
                @endforeach
                <p class="text-xs text-gray-500">{{ $system['cpu']['cores'] }} cores</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-memory mr-2 text-purple-500"></i> Memory
            </h3>
            <div class="flex items-center justify-center mb-4">
                <div class="relative w-28 h-28">
                    <svg class="w-28 h-28 transform -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                        <circle cx="50" cy="50" r="40" fill="none" stroke="{{ $system['memory']['percent'] > 80 ? '#ef4444' : '#6366f1' }}" stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="{{ $system['memory']['percent'] * 2.51 }} 251.2"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-lg font-bold text-gray-800">{{ $system['memory']['percent'] }}%</span>
                    </div>
                </div>
            </div>
            <div class="text-center text-sm text-gray-500">
                {{ $system['memory']['used'] }} / {{ $system['memory']['total'] }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-2"><i class="fas fa-hdd mr-2 text-orange-500"></i>Disk</h3>
            <p class="text-sm text-gray-600">{{ $system['disk']['used'] }} / {{ $system['disk']['total'] }}</p>
            <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                <div class="h-2 rounded-full" style="width: {{ $system['disk']['percent'] }}%; background-color: {{ $system['disk']['percent'] > 85 ? '#ef4444' : '#6366f1' }}"></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-2"><i class="fas fa-server mr-2 text-green-500"></i>System</h3>
            <p class="text-sm text-gray-600">{{ $system['os'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Kernel: {{ $system['kernel'] }}</p>
            <p class="text-xs text-gray-500">Hostname: {{ $system['hostname'] }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-2"><i class="fas fa-clock mr-2 text-blue-500"></i>Uptime</h3>
            <p class="text-sm text-gray-600">{{ $system['uptime'] }}</p>
        </div>
    </div>
</div>
@endsection
