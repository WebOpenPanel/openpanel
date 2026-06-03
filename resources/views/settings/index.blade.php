@extends('layouts.app')
@section('title', 'OpenPanel Settings')
@section('content')
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-user mr-2 text-indigo-500"></i>Profile Information</h3>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Username</span><span class="font-medium">{{ auth()->user()->username }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">UID</span><span class="font-medium">{{ auth()->user()->uid }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Home</span><span class="font-medium font-mono">{{ auth()->user()->home }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Shell</span><span class="font-medium font-mono">{{ auth()->user()->shell }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Role</span><span class="font-medium"><span class="px-2 py-0.5 text-xs bg-indigo-100 text-indigo-700 rounded-full">{{ ucfirst(auth()->user()->role) }}</span></span></div>
            <div class="flex justify-between"><span class="text-gray-500">Email</span><span class="font-medium">{{ auth()->user()->email }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Hostname</span><span class="font-medium">{{ $hostname ?? 'N/A' }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Server IP</span><span class="font-medium font-mono">{{ $serverIp ?? 'N/A' }}</span></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-lock mr-2 text-yellow-500"></i>Change Password</h3>
        <form method="POST" action="{{ route('settings.change-password') }}" class="space-y-4">
            @csrf @method('PUT')
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <input type="password" name="current_password" required class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="password" required class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" required class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
            </div>
            <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700"><i class="fas fa-key mr-1"></i> Change Password</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-palette mr-2 text-purple-500"></i>Theme</h3>
        <form method="POST" action="{{ route('settings.change-theme') }}" class="flex gap-3">@csrf @method('PUT')
            <select name="theme" class="px-3 py-2 border rounded-lg text-sm">
                <option value="default">Default</option>
                <option value="dark">Dark</option>
            </select>
            <button class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">Apply</button>
        </form>
    </div>
</div>
@endsection
