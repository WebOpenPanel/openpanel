@extends('layouts.app')
@section('title', 'PHP-FPM Manager')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">PHP-FPM {{ $version }}</h2>
        <span class="px-2 py-1 rounded text-xs {{ $status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $status }}</span>
    </div>
    <div class="flex gap-2">
        @foreach(['start','stop','restart'] as $act)
        <form method="POST" action="{{ route('php-fpm.service') }}">
            @csrf
            <input type="hidden" name="action" value="{{ $act }}">
            <button class="bg-{{ $act === 'stop' ? 'red' : 'green' }}-600 text-white px-3 py-1 rounded text-sm">{{ ucfirst($act) }}</button>
        </form>
        @endforeach
        <a href="{{ route('php-fpm.config') }}" class="bg-gray-600 text-white px-3 py-1 rounded text-sm">Edit Config</a>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Pools</h3>
        @foreach($pools as $pool)
        <a href="{{ route('php-fpm.pool', $pool) }}" class="inline-block bg-gray-100 rounded px-3 py-1 text-sm mr-1 mb-1 hover:bg-gray-200">{{ $pool }}</a>
        @endforeach
    </div>
</div>
@endsection
