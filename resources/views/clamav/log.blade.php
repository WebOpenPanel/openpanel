@extends('layouts.app')
@section('title', 'ClamAV Scan Log')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Scan Log</h1>
    <div class="bg-white rounded-lg shadow p-6"><pre class="text-xs font-mono bg-gray-50 p-4 rounded max-h-[600px] overflow-y-auto">{{ $content }}</pre></div>
    <a href="{{ route('clamav.index') }}" class="inline-block mt-4 text-blue-600">Back to ClamAV</a>
</div>
@endsection
