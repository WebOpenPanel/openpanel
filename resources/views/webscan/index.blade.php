@extends('layouts.app')
@section('title', 'Web Scan')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Web Vulnerability Scanner</h1>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Scan Domain</h2>
        <form method="POST" action="{{ route('webscan.scan') }}">@csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input type="text" name="domain" placeholder="domain.com" class="border rounded p-2" required>
                <input type="text" name="doc_root" placeholder="/home/user/public_html (optional)" class="border rounded p-2">
                <button class="bg-blue-600 text-white px-4 py-2 rounded">Scan</button>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <h2 class="text-lg font-semibold p-6 pb-0">Scan History</h2>
        <table class="w-full text-sm mt-4">
            <thead><tr class="bg-gray-50 border-b"><th class="text-left px-4 py-3">Domain</th><th class="text-center px-4 py-3">Score</th><th class="text-center px-4 py-3">Issues</th><th class="text-left px-4 py-3">Scanned</th><th class="text-right px-4 py-3"></th></tr></thead>
            <tbody>
            @forelse($history as $h)
            <tr class="border-b"><td class="px-4 py-2 font-mono">{{ $h['domain'] }}</td><td class="px-4 py-2 text-center"><span class="font-bold {{ $h['score'] >= 80 ? 'text-green-600' : ($h['score'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">{{ $h['score'] }}</span></td><td class="px-4 py-2 text-center">{{ $h['issues'] }}</td><td class="px-4 py-2 text-xs">{{ $h['scanned_at'] }}</td><td class="px-4 py-2 text-right"><a href="{{ route('webscan.results', $h['domain']) }}" class="text-blue-600 text-sm">View</a></td></tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No scans yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
