@extends('layouts.app')
@section('title', 'Generate SSL Certificate')
@section('content')
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-lock mr-2 text-green-500"></i>Generate Self-Signed Certificate</h3>
        <form method="POST" action="{{ route('ssl.generate-self-signed') }}" class="space-y-4">
            @csrf
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                <input type="text" name="domain" required placeholder="example.com" class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">User Account (optional)</label>
                <select name="user_account_id" class="w-full px-3 py-2.5 border rounded-lg text-sm"><option value="">None</option>
                    @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->domain }}</option>@endforeach</select></div>
            <button type="submit" class="px-5 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700"><i class="fas fa-key mr-2"></i> Generate Self-Signed SSL</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-upload mr-2 text-blue-500"></i>Install Custom SSL</h3>
        <form method="POST" action="{{ route('ssl.install') }}" class="space-y-4">
            @csrf
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                <input type="text" name="domain" required class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Certificate (CRT)</label>
                <textarea name="certificate" required rows="4" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono text-xs" placeholder="-----BEGIN CERTIFICATE-----"></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Private Key (KEY)</label>
                <textarea name="private_key" required rows="4" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono text-xs" placeholder="-----BEGIN PRIVATE KEY-----"></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">CA Bundle (optional)</label>
                <textarea name="ca_bundle" rows="3" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono text-xs"></textarea></div>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700"><i class="fas fa-upload mr-2"></i> Install Certificate</button>
        </form>
    </div>
</div>
@endsection
