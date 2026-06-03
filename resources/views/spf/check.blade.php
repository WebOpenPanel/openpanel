@extends('layouts.app')
@section('title', 'SPF Check: ' . $domain)
@section('content')
<div class="space-y-4">
    <h2 class="text-lg font-semibold">SPF/DMARC Check: {{ $domain }}</h2>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold">SPF</h3>
        <pre class="bg-gray-100 p-3 rounded text-sm mt-2">{{ $spf ?: 'No SPF record found.' }}</pre>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold">DMARC</h3>
        <pre class="bg-gray-100 p-3 rounded text-sm mt-2">{{ $dmarc ?: 'No DMARC record found.' }}</pre>
    </div>
</div>
@endsection
