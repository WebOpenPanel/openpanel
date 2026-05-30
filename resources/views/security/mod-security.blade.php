@extends('layouts.app')
@section('title', 'ModSecurity')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.mod-security') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">ModSecurity</a>
        <a href="{{ route('security.csf') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">CSF</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-shield-alt mr-2 text-indigo-500"></i>ModSecurity Status</h3>
        <div class="flex items-center gap-4 mb-4">
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ ($status['installed'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ ($status['installed'] ?? false) ? 'Installed' : 'Not Installed' }}</span>
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ ($status['enabled'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ($status['enabled'] ?? false) ? 'Enabled' : 'Detection Only' }}</span>
        </div>
        <form method="POST" action="{{ route('security.mod-security-toggle') }}" class="flex gap-2">
            @csrf
            <button name="enabled" value="1" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">Enable</button>
            <button name="enabled" value="0" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700">Disable</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">ModSecurity Rules</h3>
        <form method="POST" action="{{ route('security.mod-security-save-rules') }}" class="space-y-3">
            @csrf
            <textarea name="content" rows="20" class="w-full px-3 py-2 border rounded-lg text-sm font-mono">{{ $rules }}</textarea>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save Rules</button>
        </form>
    </div>
</div>
@endsection
