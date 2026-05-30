@extends('layouts.app')
@section('title', 'IPTables')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.csf') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">CSF</a>
        <a href="{{ route('security.iptables') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">IPTables</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700"><i class="fas fa-filter mr-2 text-indigo-500"></i>IPTables Rules</h3>
            <form method="POST" action="{{ route('security.iptables-flush') }}" onsubmit="return confirm('Flush all iptables rules?')">@csrf<button class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700">Flush All</button></form>
        </div>
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-96 font-mono">{{ $rules }}</pre>
    </div>
</div>
@endsection
