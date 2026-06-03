@extends('layouts.app')
@section('title', 'Panel SSL')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('ssl.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Certificates</a>
        <a href="{{ route('ssl.generate') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Generate</a>
        <a href="{{ route('ssl.letsencrypt') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Let's Encrypt</a>
        <a href="{{ route('ssl.panel') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Panel SSL</a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-shield-alt mr-2 text-indigo-500"></i>Current Panel Certificate</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Type:</span>
                    <span class="font-medium {{ $certInfo['is_letsencrypt'] ? 'text-green-600' : 'text-yellow-600' }}">
                        {{ strtoupper($certInfo['type']) }}
                        @if($certInfo['is_letsencrypt']) <i class="fas fa-check-circle ml-1"></i> @endif
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Subject:</span>
                    <span class="font-medium text-gray-800">{{ $certInfo['subject'] }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Issuer:</span>
                    <span class="font-medium text-gray-800">{{ $certInfo['issuer'] }}</span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Valid From:</span>
                    <span class="text-gray-800">{{ $certInfo['valid_from'] ?? '-' }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Expires:</span>
                    <span class="text-gray-800">
                        {{ $certInfo['valid_to'] ?? '-' }}
                        @if($certInfo['days_remaining'] !== null)
                            @if($certInfo['days_remaining'] < 7)
                                <span class="text-red-500 ml-1">({{ $certInfo['days_remaining'] }}d left)</span>
                            @elseif($certInfo['days_remaining'] < 30)
                                <span class="text-yellow-500 ml-1">({{ $certInfo['days_remaining'] }}d left)</span>
                            @else
                                <span class="text-green-500 ml-1">({{ $certInfo['days_remaining'] }}d left)</span>
                            @endif
                        @endif
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Serial:</span>
                    <span class="text-gray-600 font-mono text-xs">{{ $certInfo['serial'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-globe mr-2 text-blue-500"></i>DNS Validation</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
                <span class="text-gray-500">Hostname:</span>
                <span class="font-medium ml-1">{{ $hostname }}</span>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <span class="text-gray-500">Server IP:</span>
                <span class="font-medium ml-1">{{ $dns['server_ip'] }}</span>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <span class="text-gray-500">DNS IP:</span>
                <span class="font-medium ml-1 {{ $dns['matches'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $dns['dns_ip'] ?: 'Not resolved' }}
                    @if($dns['matches']) <i class="fas fa-check ml-1"></i> @elseif($dns['dns_ip']) <i class="fas fa-times ml-1"></i> @endif
                </span>
            </div>
        </div>
    </div>

    @if(!$certbotInstalled)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h4 class="text-sm font-semibold text-yellow-800"><i class="fas fa-exclamation-triangle mr-2"></i>Certbot Not Installed</h4>
                <p class="text-xs text-yellow-700 mt-1">Install certbot to issue Let's Encrypt certificates.</p>
            </div>
            <form method="POST" action="{{ route('ssl.panel-install-certbot') }}">
                @csrf
                <button class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700">Install Certbot</button>
            </form>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-lock mr-2 text-green-500"></i>Issue Let's Encrypt Certificate</h3>
        @if(!$dns['matches'])
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3 text-sm text-red-700">
            <i class="fas fa-exclamation-circle mr-1"></i>
            DNS mismatch detected. The hostname <strong>{{ $hostname }}</strong> must point to <strong>{{ $dns['server_ip'] }}</strong> before issuing a certificate.
        </div>
        @endif
        <form method="POST" action="{{ route('ssl.panel-issue') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hostname</label>
                    <input type="text" name="hostname" value="{{ $hostname }}" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email (for Let's Encrypt)</label>
                    <input type="email" name="email" placeholder="admin@{{ $hostname }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 {{ !$certbotInstalled || !$dns['matches'] ? 'opacity-50 cursor-not-allowed' : '' }}"
                {{ !$certbotInstalled || !$dns['matches'] ? 'disabled' : '' }}>
                <i class="fas fa-certificate mr-1"></i> Issue Certificate
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Renew Certificate</h3>
            <form method="POST" action="{{ route('ssl.panel-renew') }}">
                @csrf
                <button class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                    <i class="fas fa-sync mr-1"></i> Renew Now
                </button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Revoke & Fall Back</h3>
            <form method="POST" action="{{ route('ssl.panel-revoke') }}" onsubmit="return confirm('Revoke LE cert and revert to self-signed?')">
                @csrf
                <button class="w-full px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">
                    <i class="fas fa-ban mr-1"></i> Revoke → Self-Signed
                </button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Regenerate Self-Signed</h3>
            <form method="POST" action="{{ route('ssl.panel-self-signed') }}" onsubmit="return confirm('Replace current cert with self-signed?')">
                @csrf
                <button class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700">
                    <i class="fas fa-redo mr-1"></i> Self-Signed
                </button>
            </form>
        </div>
    </div>

    @if(session('output'))
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Output</h3>
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ session('output') }}</pre>
    </div>
    @endif
</div>
@endsection