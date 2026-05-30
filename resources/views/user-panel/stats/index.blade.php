@extends('user-layouts.app')

@section('title', 'Statistics')

@section('content')
<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-hard-drive text-blue-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Disk Used</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $diskUsed }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-microchip text-green-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Running Processes</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $processCount }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-sign-in-alt text-purple-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Last Login</p>
                    <p class="text-sm font-medium text-gray-800">{{ $lastLogin ? Str::limit($lastLogin, 40) : 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-chart-pie mr-2 text-indigo-600"></i>Disk Usage Breakdown</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @foreach($diskBreakdown as $dir => $size)
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-sm text-gray-500">{{ $dir }}</p>
                    <p class="text-lg font-bold text-gray-800">{{ $size }}</p>
                </div>
            @endforeach
            @if(empty($diskBreakdown))
                <p class="text-gray-500 text-sm col-span-5">No data available.</p>
            @endif
        </div>
    </div>
</div>
@endsection
