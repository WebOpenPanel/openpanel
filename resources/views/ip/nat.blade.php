@extends('layouts.app')
@section('title', 'NAT Configuration')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('ip.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">IPs</a>
        <a href="{{ route('ip.nat') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">NAT</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-exchange-alt mr-2 text-indigo-500"></i>NAT Configuration</h3>
        <form method="POST" action="{{ route('ip.save-nat') }}" class="space-y-3">
            @csrf
            <div><label class="block text-sm font-medium text-gray-700 mb-1">NAT IP</label><input type="text" name="nat_ip" value="{{ $natIp ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save</button>
        </form>
        @if(!empty($config))
        <div class="mt-4"><pre class="bg-gray-50 p-3 rounded text-xs font-mono">{{ json_encode($config, JSON_PRETTY_PRINT) }}</pre></div>
        @endif
    </div>
</div>
@endsection
