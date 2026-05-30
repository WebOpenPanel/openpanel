@extends('layouts.app')
@section('title', 'Nameservers')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('dns.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">DNS Zones</a>
        <a href="{{ route('dns.nameservers') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Nameservers</a>
        <a href="{{ route('dns.templates') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Templates</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-server mr-2 text-indigo-500"></i>Nameserver Configuration</h3>
        <form method="POST" action="{{ route('dns.save-nameservers') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">NS1</label><input type="text" name="ns1" value="{{ $nameservers['ns1'] ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">NS1 IP</label><input type="text" name="ns1_ip" value="{{ $nameservers['ns1_ip'] ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">NS2</label><input type="text" name="ns2" value="{{ $nameservers['ns2'] ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">NS2 IP</label><input type="text" name="ns2_ip" value="{{ $nameservers['ns2_ip'] ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save Nameservers</button>
        </form>
    </div>
</div>
@endsection
