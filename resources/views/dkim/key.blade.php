@extends('layouts.app')
@section('title', 'DKIM Key: ' . $domain)
@section('content')
<div class="space-y-4">
    <h2 class="text-lg font-semibold">DKIM Record for {{ $domain }}</h2>
    <pre class="bg-gray-100 p-4 rounded text-sm break-all">{{ $dns }}</pre>
    <p class="text-sm text-gray-600">Add this as a TXT record in your DNS zone.</p>
</div>
@endsection
