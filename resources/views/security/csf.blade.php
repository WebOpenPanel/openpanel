@extends('layouts.app')
@section('title', 'CSF Firewall')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('security.firewall') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Firewall</a>
        <a href="{{ route('security.csf') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">CSF</a>
        <a href="{{ route('security.csf-config') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">CSF Config</a>
        <a href="{{ route('security.blocked-ips') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Blocked IPs</a>
        <a href="{{ route('security.iptables') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">IPTables</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-shield-alt mr-2 text-indigo-500"></i>CSF Actions</h3>
        <form method="POST" action="{{ route('security.csf-action') }}" class="flex flex-wrap gap-2 mb-4">
            @csrf
            <button name="action" value="enable" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">Enable</button>
            <button name="action" value="disable" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700">Disable</button>
            <button name="action" value="restart" class="px-3 py-1.5 bg-yellow-600 text-white rounded-lg text-xs hover:bg-yellow-700">Restart</button>
            <button name="action" value="quick_r" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700">Quick Restart</button>
            <button name="action" value="flush_all" class="px-3 py-1.5 bg-red-700 text-white rounded-lg text-xs hover:bg-red-800">Flush All</button>
            <button name="action" value="csf_test" class="px-3 py-1.5 bg-gray-600 text-white rounded-lg text-xs hover:bg-gray-700">Test</button>
            <button name="action" value="csf_update" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs hover:bg-indigo-700">Update</button>
        </form>
        @if(session('output'))
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-48 font-mono mb-4">{{ session('output') }}</pre>
        @endif
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Allow IP</h3>
            <form method="POST" action="{{ route('security.csf-allow') }}" class="flex gap-2">@csrf
                <input type="text" name="ip" placeholder="IP Address" class="flex-1 px-3 py-2 border rounded-lg text-sm" required>
                <input type="text" name="comment" placeholder="Comment" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                <button class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">Allow</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Deny IP</h3>
            <form method="POST" action="{{ route('security.csf-deny') }}" class="flex gap-2">@csrf
                <input type="text" name="ip" placeholder="IP Address" class="flex-1 px-3 py-2 border rounded-lg text-sm" required>
                <input type="text" name="comment" placeholder="Comment" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                <button class="px-3 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">Deny</button>
            </form>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Unblock IP</h3>
            <form method="POST" action="{{ route('security.csf-unblock') }}" class="flex gap-2">@csrf
                <input type="text" name="ip" placeholder="IP Address" class="flex-1 px-3 py-2 border rounded-lg text-sm" required>
                <button class="px-3 py-2 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700">Unblock</button>
            </form>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Firewall Status</h3>
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ $status }}</pre>
    </div>
</div>
@endsection
