@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">SSL Info: {{ $domain }}</h1>
    @if($info)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4">
            <div><strong>Subject:</strong> {{ $info['subject'] }}</div>
            <div><strong>Issuer:</strong> {{ $info['issuer'] }}</div>
            <div><strong>Valid From:</strong> {{ $info['valid_from'] }}</div>
            <div><strong>Valid To:</strong> {{ $info['valid_to'] }}</div>
            <div><strong>Serial:</strong> {{ $info['serial'] }}</div>
            <div><strong>SAN:</strong> {{ $info['san'] }}</div>
        </div>
    </div>
    @else
    <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-6">No SSL certificate found on disk for this domain.</div>
    @endif
    @if($dbCert)
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Database Record</h2>
        <div class="grid grid-cols-2 gap-4">
            <div><strong>Type:</strong> {{ $dbCert->type }}</div>
            <div><strong>Status:</strong> {{ $dbCert->status }}</div>
            <div><strong>Issued:</strong> {{ $dbCert->issued_at }}</div>
            <div><strong>Expires:</strong> {{ $dbCert->expires_at }}</div>
        </div>
    </div>
    @endif
</div>
@endsection