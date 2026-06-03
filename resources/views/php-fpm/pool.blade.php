@extends('layouts.app')
@section('title', 'PHP-FPM Pool: ' . $pool)
@section('content')
<div class="space-y-4">
    <h2 class="text-lg font-semibold">Pool: {{ $pool }}</h2>
    <form method="POST" action="{{ route('php-fpm.save-pool') }}">
        @csrf
        <input type="hidden" name="pool" value="{{ $pool }}">
        <textarea name="config" rows="25" class="w-full font-mono text-sm border rounded p-3">{{ $config }}</textarea>
        <button class="mt-2 bg-blue-600 text-white px-4 py-2 rounded">Save & Restart</button>
    </form>
</div>
@endsection
