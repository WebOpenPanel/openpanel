@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Tomcat Manager</h1>
    @if(!$installed)<div class="bg-yellow-50 border border-yellow-200 rounded p-4">Tomcat is not installed.</div>
    <form method="POST" action="{{ route('tomcat.install') }}" class="mt-4">@csrf<button class="bg-green-600 text-white px-4 py-2 rounded">Install Tomcat</button></form>
    @else
    <div class="flex gap-2 mb-6">
        <form method="POST" action="{{ route('tomcat.start') }}">@csrf<button class="bg-green-600 text-white px-3 py-1 rounded text-sm">Start</button></form>
        <form method="POST" action="{{ route('tomcat.stop') }}">@csrf<button class="bg-red-600 text-white px-3 py-1 rounded text-sm">Stop</button></form>
        <form method="POST" action="{{ route('tomcat.restart') }}">@csrf<button class="bg-yellow-600 text-white px-3 py-1 rounded text-sm">Restart</button></form>
    </div>
    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Users</h2>
            @foreach($users as $u)<div class="flex justify-between border-b py-2"><span>{{ $u['username'] }} ({{ $u['roles'] }})</span>
            <form method="POST" action="{{ route('tomcat.delete-user') }}">@csrf @method('DELETE')<input type="hidden" name="username" value="{{ $u['username'] }}"><button class="text-red-600 text-sm">Delete</button></form></div>@endforeach
            <form method="POST" action="{{ route('tomcat.add-user') }}" class="mt-4 flex gap-2">@csrf
                <input type="text" name="username" placeholder="User" class="border rounded p-2 flex-1">
                <input type="password" name="password" placeholder="Pass" class="border rounded p-2 flex-1">
                <button class="bg-blue-600 text-white px-3 py-2 rounded">Add</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Deployed Apps</h2>
            @foreach($apps as $a)<div class="border-b py-2">{{ $a['name'] }}</div>@endforeach
        </div>
    </div>@endif
</div>
@endsection