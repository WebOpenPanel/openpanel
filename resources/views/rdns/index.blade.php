@extends('layouts.app')
@section('title', 'rDNS Checker')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-search mr-2"></i>rDNS Checker</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-600 mb-4">Check reverse DNS (PTR) records for IP addresses. PTR records should match your server hostname for proper email delivery.</p>
        <div class="mb-4 text-sm text-gray-500">Server IP: <span class="font-mono text-gray-800">{{ $serverIp }}</span></div>
        <form id="rdns-form" class="flex gap-4" onsubmit="event.preventDefault(); checkRdns();">
            <input type="text" id="rdns-ip" value="{{ $serverIp }}" placeholder="IP address" class="flex-1 border rounded-lg px-3 py-2" required>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Check rDNS</button>
        </form>
        <div id="rdns-result" class="mt-4 hidden">
            <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                <div class="flex justify-between"><span class="text-gray-600">IP:</span><span id="res-ip" class="font-mono"></span></div>
                <div class="flex justify-between"><span class="text-gray-600">PTR Record:</span><span id="res-ptr" class="font-mono"></span></div>
                <div class="flex justify-between"><span class="text-gray-600">Server Hostname:</span><span id="res-hostname" class="font-mono"></span></div>
                <div class="flex justify-between"><span class="text-gray-600">Match:</span><span id="res-match" class="font-mono"></span></div>
            </div>
        </div>
    </div>
</div>
<script>
function checkRdns() {
    const ip = document.getElementById('rdns-ip').value;
    fetch('{{ route("rdns.check") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: JSON.stringify({ ip: ip })
    }).then(r => r.json()).then(data => {
        document.getElementById('rdns-result').classList.remove('hidden');
        document.getElementById('res-ip').textContent = data.ip;
        document.getElementById('res-ptr').textContent = data.ptr_record;
        document.getElementById('res-hostname').textContent = data.server_hostname;
        const matchEl = document.getElementById('res-match');
        matchEl.textContent = data.match ? 'YES' : 'NO';
        matchEl.className = 'font-mono ' + (data.match ? 'text-green-600' : 'text-red-600');
    });
}
</script>
@endsection
