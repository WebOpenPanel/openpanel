@extends('layouts.app')
@section('title', 'Add DNS Zone')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-plus-circle mr-2"></i>Add DNS Zone</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-600 mb-4">Create a new DNS zone with standard records (A, NS, MX, TXT). The zone will be added to BIND named.conf.</p>
        <form action="{{ route('dns-zone-add.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Domain Name</label>
                    <input type="text" name="domain" placeholder="example.com" class="w-full border rounded-lg px-3 py-2 mt-1" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">IP Address</label>
                    <input type="text" name="ip" value="{{ $serverIp }}" class="w-full border rounded-lg px-3 py-2 mt-1" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Admin Email (optional)</label>
                    <input type="email" name="email" placeholder="admin@example.com" class="w-full border rounded-lg px-3 py-2 mt-1">
                </div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
                <p class="font-medium mb-2">Records that will be created:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>A record: @ → IP</li>
                    <li>A record: ns1, ns2, www, mail, ftp → IP</li>
                    <li>NS records: ns1, ns2</li>
                    <li>MX record: mail (priority 10)</li>
                    <li>TXT record: SPF</li>
                </ul>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700" onclick="return confirm('Create DNS zone?')">Create Zone</button>
        </form>
    </div>
</div>
@endsection
