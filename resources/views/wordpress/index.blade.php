@extends('layouts.app')
@section('title', 'WordPress Manager')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">WordPress Manager</h2>
            <p class="text-sm text-gray-500">Manage all WordPress installations</p>
        </div>
        <a href="{{ route('wordpress.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-1"></i> Install WordPress
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    @if($sites->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border p-12 text-center">
            <i class="fab fa-wordpress text-4xl text-gray-300 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No WordPress Sites</h3>
            <p class="text-sm text-gray-500 mb-4">Install your first WordPress site to get started.</p>
            <a href="{{ route('wordpress.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                Install WordPress
            </a>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">WP Version</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PHP</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stack</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SSL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Redis</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($sites as $site)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <a href="{{ route('wordpress.show', $site->id) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ $site->domain }}</a>
                            <div class="text-xs text-gray-500 mt-1">{{ $site->site_url }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $site->wp_version ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $site->php_version }}</td>
                        <td class="px-6 py-4 text-xs text-gray-500">{{ $site->stack_name }}</td>
                        <td class="px-6 py-4">
                            @if($site->status === 'active')
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Active</span>
                            @elseif($site->status === 'suspended')
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">Suspended</span>
                            @else
                                <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">{{ ucfirst($site->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($site->ssl_enabled)
                                <i class="fas fa-lock text-green-500"></i>
                            @else
                                <i class="fas fa-unlock text-gray-400"></i>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($site->redis_enabled)
                                <i class="fas fa-bolt text-red-500"></i>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('wordpress.show', $site->id) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Manage</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
