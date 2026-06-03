@extends('layouts.app')
@section('title', 'SPF / DMARC')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Recommended SPF Record</h3>
        <pre class="bg-gray-100 p-3 rounded text-sm">{{ $spfRecord }}</pre>
        <p class="text-xs text-gray-500 mt-1">Add as TXT record for your domain.</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Recommended DMARC Record</h3>
        <pre class="bg-gray-100 p-3 rounded text-sm">{{ $dmarcRecord }}</pre>
        <p class="text-xs text-gray-500 mt-1">Add as TXT record for _dmarc.yourdomain.com</p>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Check Domain</h3>
        <form method="POST" action="{{ route('spf.check') }}" class="flex gap-2">
            @csrf
            <input name="domain" placeholder="domain.com" class="border rounded px-3 py-1 flex-1" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Check</button>
        </form>
    </div>
</div>
@endsection
