@extends('layouts.app')
@section('title', 'Nameservers')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Nameserver Configuration</h3>
        <form method="POST" action="{{ route('nameservers.update') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">NS1</label>
                    <input name="ns1" value="{{ $nameservers['ns1'] ?? '' }}" class="w-full border rounded px-3 py-1">
                </div>
                <div>
                    <label class="text-sm text-gray-600">NS1 IP</label>
                    <input name="ns1_ip" value="{{ $nameservers['ns1_ip'] ?? $serverIp }}" class="w-full border rounded px-3 py-1">
                </div>
                <div>
                    <label class="text-sm text-gray-600">NS2</label>
                    <input name="ns2" value="{{ $nameservers['ns2'] ?? '' }}" class="w-full border rounded px-3 py-1">
                </div>
                <div>
                    <label class="text-sm text-gray-600">NS2 IP</label>
                    <input name="ns2_ip" value="{{ $nameservers['ns2_ip'] ?? $serverIp }}" class="w-full border rounded px-3 py-1">
                </div>
            </div>
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Save Nameservers</button>
        </form>
    </div>
</div>
@endsection
