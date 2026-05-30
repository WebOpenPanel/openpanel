@extends('user-layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-globe text-blue-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Domains</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $domains }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-database text-green-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Databases</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $databases }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-envelope text-purple-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Email Accounts</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $emailAccounts }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-import text-orange-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">FTP Accounts</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $ftpAccounts }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-hard-drive mr-2 text-blue-600"></i>Disk Usage</h3>
            <div class="space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Used</span>
                    <span class="font-medium text-gray-800">{{ $diskUsed }} MB</span>
                </div>
                @if($diskQuota > 0)
                    @php $percent = min(100, round(($diskUsed / $diskQuota) * 100)); @endphp
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="h-3 rounded-full {{ $percent > 90 ? 'bg-red-500' : ($percent > 70 ? 'bg-yellow-500' : 'bg-blue-500') }}" style="width: {{ $percent }}%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>{{ $percent }}% used</span>
                        <span>{{ $diskQuota }} MB quota</span>
                    </div>
                @else
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="h-3 rounded-full bg-blue-500" style="width: 10%"></div>
                    </div>
                    <p class="text-xs text-gray-500">No quota set</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-user mr-2 text-emerald-600"></i>Account Info</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Username</span>
                    <span class="font-medium text-gray-800">{{ $username }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Home Directory</span>
                    <span class="font-medium text-gray-800">/home/{{ $username }}</span>
                </div>
                @if($package)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Package</span>
                        <span class="font-medium text-gray-800">{{ $package->package ?? 'Default' }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-link mr-2 text-indigo-600"></i>Quick Links</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('user.files.index') }}" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <i class="fas fa-folder-open text-blue-500 mr-3"></i>
                <span class="text-sm font-medium text-gray-700">File Manager</span>
            </a>
            <a href="{{ route('user.phpmyadmin') }}" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors" target="_blank">
                <i class="fas fa-database text-green-500 mr-3"></i>
                <span class="text-sm font-medium text-gray-700">phpMyAdmin</span>
            </a>
            <a href="{{ route('user.email.index') }}" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <i class="fas fa-envelope text-purple-500 mr-3"></i>
                <span class="text-sm font-medium text-gray-700">Webmail</span>
            </a>
            <a href="{{ route('user.stats.index') }}" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                <i class="fas fa-chart-line text-orange-500 mr-3"></i>
                <span class="text-sm font-medium text-gray-700">Statistics</span>
            </a>
        </div>
    </div>
</div>
@endsection
