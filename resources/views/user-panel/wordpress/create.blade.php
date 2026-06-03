@extends('user-layouts.app')
@section('title', 'Install WordPress')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Install WordPress</h2>
            <p class="text-sm text-gray-500">Set up a new WordPress site</p>
        </div>
        <a href="{{ route('user.wordpress.index') }}" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('user.wordpress.store') }}" class="bg-white rounded-xl shadow-sm border p-6 space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                <select name="domain" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                    <option value="">Select domain...</option>
                    @foreach($domains as $domain)
                        <option value="{{ $domain->domain }}" {{ old('domain') == $domain->domain ? 'selected' : '' }}>{{ $domain->domain }}</option>
                    @endforeach
                </select>
                @error('domain') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Site Title</label>
                <input type="text" name="site_title" value="{{ old('site_title', 'My WordPress Site') }}" class="w-full border rounded-lg px-3 py-2 text-sm" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Username</label>
                <input type="text" name="admin_user" value="{{ old('admin_user', 'admin') }}" class="w-full border rounded-lg px-3 py-2 text-sm" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Password</label>
                <input type="password" name="admin_password" class="w-full border rounded-lg px-3 py-2 text-sm" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
                <input type="email" name="admin_email" value="{{ old('admin_email') }}" class="w-full border rounded-lg px-3 py-2 text-sm" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Install Path</label>
                <input type="text" name="install_path" value="{{ old('install_path') }}" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="/home/user/public_html">
                <p class="text-xs text-gray-500 mt-1">Leave empty for default public_html</p>
            </div>
        </div>

        <div class="border-t pt-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Options</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="ssl_enabled" value="1" {{ old('ssl_enabled') ? 'checked' : '' }} class="rounded">
                    Enable SSL
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="enable_redis" value="1" {{ old('enable_redis') ? 'checked' : '' }} class="rounded">
                    Enable Redis Object Cache
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700" onclick="return confirm('Install WordPress?')">
                <i class="fab fa-wordpress mr-1"></i> Install WordPress
            </button>
        </div>
    </form>
</div>
@endsection
