@extends('layouts.app')
@section('title', 'RBL Check - All IPs')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">RBL Check - All Server IPs</h1>
    @foreach($results as $r)
    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <div class="flex justify-between items-center mb-2">
            <span class="font-mono text-lg">{{ $r['ip'] }}</span>
            <span class="px-3 py-1 rounded {{ $r['clean'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $r['clean'] ? 'CLEAN' : 'LISTED on ' . $r['listed_count'] }}</span>
        </div>
        @if($r['listed_count'] > 0)
        <div class="flex flex-wrap gap-1">@foreach($r['results'] as $bl)@if($bl['listed'])<span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded">{{ $bl['blacklist'] }}</span>@endif@endforeach</div>
        @endif
    </div>
    @endforeach
    <a href="{{ route('rbl.index') }}" class="inline-block mt-4 text-blue-600">Back to RBL Check</a>
</div>
@endsection
