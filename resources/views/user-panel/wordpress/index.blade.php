@extends('user-layouts.app')
@section('title', 'My WordPress Sites')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">My WordPress Sites</h2>
            <p class="text-sm text-gray-500">Manage your WordPress installations</p>
        </div>
        <a href="{{ route('user.wordpress.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
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
            <a href="{{ route('user.wordpress.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">Install WordPress</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($sites as $site)
            <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">{{ $site->domain }}</h3>
                    @if($site->status === 'active')
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Active</span>
                    @elseif($site->status === 'suspended')
                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">Suspended</span>
                    @else
                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">{{ ucfirst($site->status) }}</span>
                    @endif
                </div>
                <div class="text-sm text-gray-500 space-y-1 mb-4">
                    <div>WP {{ $site->wp_version ?? '-' }} &middot; PHP {{ $site->php_version }}</div>
                    <div>{{ $site->stack_name }}</div>
                    <div class="flex gap-2 mt-2">
                        @if($site->ssl_enabled) <span class="px-1.5 py-0.5 text-xs bg-green-100 text-green-700 rounded">SSL</span> @endif
                        @if($site->redis_enabled) <span class="px-1.5 py-0.5 text-xs bg-red-100 text-red-700 rounded">Redis</span> @endif
                        @if($site->varnish_enabled) <span class="px-1.5 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">Varnish</span> @endif
                    </div>
                </div>
                <a href="{{ route('user.wordpress.show', $site->id) }}" class="block text-center px-3 py-2 bg-indigo-50 text-indigo-700 text-sm rounded-lg hover:bg-indigo-100">
                    Manage Site
                </a>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
