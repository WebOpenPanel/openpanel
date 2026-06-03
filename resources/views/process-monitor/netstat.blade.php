@extends('layouts.app')
@section('title', 'Netstat / Ports')
@section('content')
<div class="space-y-4">
    <h2 class="text-lg font-semibold">Network Connections</h2>
    <pre class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-auto">{{ $connections }}</pre>
</div>
@endsection
