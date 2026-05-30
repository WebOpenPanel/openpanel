@extends('layouts.app')
@section('title', 'Shell Access')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.login-security') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Login Security</a>
        <a href="{{ route('security.shell-access') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Shell Access</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-terminal mr-2 text-gray-600"></i>Available Shells</h3>
        <div class="space-y-2">
            @foreach($shells as $shell)
            <div class="flex items-center gap-2 text-sm"><i class="fas fa-check-circle text-green-500"></i><span class="font-mono">{{ $shell }}</span></div>
            @endforeach
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Change User Shell</h3>
        <form method="POST" action="{{ route('security.set-shell') }}" class="flex gap-3">
            @csrf
            <input type="text" name="username" placeholder="Username" class="px-3 py-2 border rounded-lg text-sm" required>
            <select name="shell" class="px-3 py-2 border rounded-lg text-sm">
                @foreach($shells as $shell)
                <option value="{{ $shell }}">{{ $shell }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Set Shell</button>
        </form>
    </div>
</div>
@endsection
