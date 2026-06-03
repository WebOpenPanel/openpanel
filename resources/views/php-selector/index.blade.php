@extends('layouts.app')
@section('title', 'PHP Selector')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Current PHP Version: <span class="text-blue-600">{{ $current }}</span></h3>
        <h4 class="text-sm text-gray-600 mb-2">Installed Versions</h4>
        <div class="flex gap-2 mb-4">
            @foreach($installed as $v)
            <form method="POST" action="{{ route('php-selector.switch') }}">
                @csrf
                <input type="hidden" name="version" value="{{ $v }}">
                <button class="px-3 py-2 rounded text-sm {{ $v === $current ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' }}">{{ $v }}</button>
            </form>
            @endforeach
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Install New Version</h3>
        <div class="flex gap-2">
            @foreach($available as $v)
            @if(!in_array($v, $installed))
            <form method="POST" action="{{ route('php-selector.install') }}">
                @csrf
                <input type="hidden" name="version" value="{{ $v }}">
                <button class="bg-green-600 text-white px-3 py-2 rounded text-sm">Install {{ $v }}</button>
            </form>
            @endif
            @endforeach
        </div>
    </div>
</div>
@endsection
