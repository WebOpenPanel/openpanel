@extends('layouts.app')
@section('title', 'FFmpeg Installer')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-film mr-2"></i>FFmpeg Installer</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold">FFmpeg Status</h2>
                <p class="text-sm text-gray-500 mt-1">Multimedia framework for video/audio processing</p>
            </div>
            <span class="px-3 py-1 text-sm rounded {{ $installed ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ $installed ? 'INSTALLED' : 'NOT INSTALLED' }}</span>
        </div>
        @if($installed && $version)
        <pre class="mt-4 bg-gray-100 p-3 rounded text-sm text-gray-700">{{ $version }}</pre>
        @endif
        <div class="mt-4 flex gap-4">
            @if(!$installed)
            <form action="{{ route('ffmpeg.install') }}" method="POST">@csrf<button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">Install FFmpeg</button></form>
            @else
            <form action="{{ route('ffmpeg.uninstall') }}" method="POST" onsubmit="return confirm('Remove FFmpeg?')">@csrf<button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">Remove FFmpeg</button></form>
            @endif
        </div>
    </div>
</div>
@endsection
