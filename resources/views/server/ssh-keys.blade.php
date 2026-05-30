@extends('layouts.app')
@section('title', 'SSH Keys')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('server.ssh-keys') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">SSH Keys</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-key mr-2 text-indigo-500"></i>Generate SSH Key</h3>
        <form method="POST" action="{{ route('server.generate-ssh-key') }}" class="flex gap-3">
            @csrf
            <select name="type" class="px-3 py-2 border rounded-lg text-sm">
                <option value="rsa">RSA</option>
                <option value="ed25519">Ed25519</option>
                <option value="ecdsa">ECDSA</option>
            </select>
            <select name="bits" class="px-3 py-2 border rounded-lg text-sm">
                <option value="2048">2048</option>
                <option value="4096" selected>4096</option>
            </select>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Generate</button>
        </form>
        @if(session('key'))
        <div class="mt-3"><pre class="bg-gray-50 p-3 rounded text-xs font-mono">{{ session('key') }}</pre></div>
        @endif
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Authorized Keys</h3>
        <pre class="bg-gray-50 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ $keys['authorized_keys'] ?? '' }}</pre>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Public Keys</h3>
        <pre class="bg-gray-50 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ $keys['public_keys'] ?? '' }}</pre>
    </div>
</div>
@endsection
