@extends('layouts.app')
@section('title', 'Screen Manager')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-terminal mr-2"></i>Screen Manager</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800">GNU Screen is not installed.</p>
        <form action="{{ route('screen.install') }}" method="POST" class="mt-3">@csrf<button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Install Screen</button></form>
    </div>
    @else
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Active Sessions</h2>
        @if(empty($sessions))
        <p class="text-gray-500">No active screen sessions.</p>
        @else
        <div class="space-y-2">
            @foreach($sessions as $session)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="font-mono text-sm">{{ $session }}</span>
                <form action="{{ route('screen.kill') }}" method="POST" onsubmit="return confirm('Kill this session?')">@csrf<input type="hidden" name="session" value="{{ $session }}"><button type="submit" class="text-red-600 hover:text-red-800 text-sm">Kill</button></form>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Create New Session</h2>
        <form action="{{ route('screen.create') }}" method="POST" class="flex gap-4">@csrf
            <input type="text" name="name" placeholder="session-name" pattern="[a-zA-Z0-9_-]+" class="flex-1 border rounded-lg px-3 py-2" required>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Create</button>
        </form>
    </div>
    @endif
</div>
@endsection
