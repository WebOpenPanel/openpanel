@extends('layouts.app')
@section('title', 'Webserver')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('server.webserver') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Webserver</a>
        <a href="{{ route('server.php') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">PHP</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-globe mr-2 text-indigo-500"></i>Web Server</h3>
        <div class="mb-3"><span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-medium">Active: {{ ucfirst($active) }}</span></div>
        <form method="POST" action="{{ route('server.set-webserver') }}" class="flex gap-2 mb-4">
            @csrf
            <select name="server" class="px-3 py-2 border rounded-lg text-sm">
                <option value="apache" {{ $active === 'apache' ? 'selected' : '' }}>Apache</option>
                <option value="nginx" {{ $active === 'nginx' ? 'selected' : '' }}>Nginx</option>
                <option value="nginx_apache" {{ $active === 'nginx_apache' ? 'selected' : '' }}>Nginx + Apache</option>
                <option value="litespeed" {{ $active === 'litespeed' ? 'selected' : '' }}>LiteSpeed</option>
            </select>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Switch</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Main Configuration</h3>
        <pre class="bg-gray-50 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ Str::limit($conf, 3000) }}</pre>
    </div>
</div>
@endsection
