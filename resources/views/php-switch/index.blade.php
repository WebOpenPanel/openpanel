@extends('layouts.app')
@section('title', 'PHP Version Switcher')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fab fa-php mr-2"></i>PHP Version Switcher</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4">
            <span class="text-sm text-gray-500">Current PHP Version:</span>
            <span class="ml-2 px-3 py-1 bg-indigo-100 text-indigo-800 rounded font-mono text-sm">{{ $current ?: 'Unknown' }}</span>
        </div>
        @if(empty($installed))
        <p class="text-gray-500">No alternative PHP versions detected. Use PHP Selector to install multiple versions.</p>
        @else
        <h3 class="text-md font-semibold mb-3">Available Versions</h3>
        <div class="space-y-2">
            @foreach($installed as $version)
            <div class="flex items-center justify-between p-3 rounded-lg {{ $version === $current ? 'bg-indigo-50 border border-indigo-200' : 'bg-gray-50' }}">
                <div>
                    <span class="font-mono text-sm">PHP {{ $version }}</span>
                    @if($version === $current)<span class="ml-2 text-xs text-indigo-600">(active)</span>@endif
                </div>
                @if($version !== $current)
                <form action="{{ route('php-switch.switch') }}" method="POST" onsubmit="return confirm('Switch to PHP {{ $version }}? This will restart php-fpm.')">
                    @csrf
                    <input type="hidden" name="version" value="{{ $version }}">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-1.5 rounded-lg hover:bg-indigo-700 text-sm">Switch</button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
