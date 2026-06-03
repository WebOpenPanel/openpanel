@extends('layouts.app')
@section('title', 'DKIM Manager')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">DKIM Manager</h2>
        <form method="POST" action="{{ route('dkim.toggle') }}">@csrf
            <button class="bg-{{ $enabled ? 'red' : 'green' }}-600 text-white px-3 py-1 rounded text-sm">{{ $enabled ? 'Disable' : 'Enable' }} OpenDKIM</button>
        </form>
    </div>
    @if($enabled)
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Generate DKIM Key</h3>
        <form method="POST" action="{{ route('dkim.generate') }}" class="flex gap-2">
            @csrf
            <input name="domain" placeholder="domain.com" class="border rounded px-3 py-1 flex-1" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Generate</button>
        </form>
        @if(session('dnsRecord'))
        <pre class="mt-3 bg-gray-100 p-3 rounded text-xs">{{ session('dnsRecord') }}</pre>
        @endif
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Configured Domains</h3>
        @foreach($keys as $key)
        <a href="{{ route('dkim.view', ['domain'=>$key]) }}" class="inline-block bg-gray-100 rounded px-3 py-1 text-sm mr-1 mb-1 hover:bg-gray-200">{{ $key }}</a>
        @endforeach
    </div>
    @endif
</div>
@endsection
