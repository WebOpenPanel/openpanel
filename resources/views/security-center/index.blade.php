@extends('layouts.app')
@section('title', 'Security Center')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-shield-alt mr-2"></i>Security Center</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">System Security Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="p-4 rounded-lg {{ $checks['ssh_root_login'] ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">SSH Root Login</span>
                    <span class="px-2 py-1 text-xs rounded {{ $checks['ssh_root_login'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">{{ $checks['ssh_root_login'] ? 'ENABLED' : 'DISABLED' }}</span>
                </div>
            </div>
            <div class="p-4 rounded-lg {{ $checks['ssh_password_auth'] ? 'bg-yellow-50 border border-yellow-200' : 'bg-green-50 border border-green-200' }}">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Password Auth</span>
                    <span class="px-2 py-1 text-xs rounded {{ $checks['ssh_password_auth'] ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">{{ $checks['ssh_password_auth'] ? 'ENABLED' : 'KEYS ONLY' }}</span>
                </div>
            </div>
            <div class="p-4 rounded-lg {{ $checks['firewall'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">CSF Firewall</span>
                    <span class="px-2 py-1 text-xs rounded {{ $checks['firewall'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $checks['firewall'] ? 'INSTALLED' : 'NOT INSTALLED' }}</span>
                </div>
            </div>
            <div class="p-4 rounded-lg {{ $checks['fail2ban'] ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' }}">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Fail2Ban</span>
                    <span class="px-2 py-1 text-xs rounded {{ $checks['fail2ban'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $checks['fail2ban'] ? 'ACTIVE' : 'INACTIVE' }}</span>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-blue-50 border border-blue-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">SELinux</span>
                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">{{ $checks['selinux'] ?: 'N/A' }}</span>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Open Ports</span>
                    <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">{{ $checks['open_ports'] }}</span>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Kernel</span>
                    <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">{{ $checks['kernel_version'] }}</span>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Uptime</span>
                    <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">{{ $checks['uptime'] }}</span>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Failed Logins (recent)</span>
                    <span class="px-2 py-1 text-xs rounded {{ (int)$checks['failed_logins'] > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">{{ $checks['failed_logins'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Quick Hardening Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if($checks['ssh_root_login'])
            <form action="{{ route('security-center.harden') }}" method="POST" onsubmit="return confirm('Disable SSH root login? Make sure you have another user with sudo access.')">
                @csrf
                <input type="hidden" name="action" value="disable-root-ssh">
                <button type="submit" class="w-full p-4 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 text-left">
                    <div class="font-medium text-red-800">Disable SSH Root Login</div>
                    <div class="text-sm text-red-600 mt-1">Prevent direct root SSH access</div>
                </button>
            </form>
            @endif

            @if($checks['ssh_password_auth'])
            <form action="{{ route('security-center.harden') }}" method="POST" onsubmit="return confirm('Disable password authentication? Ensure SSH keys are configured first.')">
                @csrf
                <input type="hidden" name="action" value="disable-password-auth">
                <button type="submit" class="w-full p-4 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 text-left">
                    <div class="font-medium text-yellow-800">Disable Password Auth</div>
                    <div class="text-sm text-yellow-600 mt-1">Require SSH key authentication only</div>
                </button>
            </form>
            @endif

            @if(!$checks['fail2ban'])
            <form action="{{ route('security-center.harden') }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="enable-fail2ban">
                <button type="submit" class="w-full p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 text-left">
                    <div class="font-medium text-green-800">Install & Enable Fail2Ban</div>
                    <div class="text-sm text-green-600 mt-1">Auto-ban brute force attackers</div>
                </button>
            </form>
            @endif

            <form action="{{ route('security-center.harden') }}" method="POST" onsubmit="return confirm('Run security package updates?')">
                @csrf
                <input type="hidden" name="action" value="update-packages">
                <button type="submit" class="w-full p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 text-left">
                    <div class="font-medium text-blue-800">Update Security Packages</div>
                    <div class="text-sm text-blue-600 mt-1">Apply latest security patches</div>
                </button>
            </form>
        </div>
    </div>

    @if(!empty($checks['last_logins']))
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Recent Logins</h2>
        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-auto text-sm font-mono" style="max-height:300px">{{ $checks['last_logins'] }}</pre>
    </div>
    @endif
</div>
@endsection
