@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Migration</h1>
    @if(session('output'))<pre class="bg-gray-900 text-green-400 p-4 rounded mb-4 text-sm">{{ session('output') }}</pre>@endif
    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Server Transfer</h2>
            <form method="POST" action="{{ route('migration.server') }}">@csrf
                <div class="space-y-3">
                    <input type="text" name="remote_host" placeholder="Remote Host" class="w-full border rounded p-2" required>
                    <input type="text" name="remote_port" value="22" placeholder="Port" class="w-full border rounded p-2">
                    <input type="text" name="remote_user" value="root" placeholder="User" class="w-full border rounded p-2">
                    <input type="text" name="username" placeholder="Username" class="w-full border rounded p-2" required>
                    <textarea name="remote_key" placeholder="SSH Key (optional, base64)" rows="3" class="w-full border rounded p-2"></textarea>
                </div>
                <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">Transfer</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">cPanel Transfer</h2>
            <form method="POST" action="{{ route('migration.cpanel') }}">@csrf
                <div class="space-y-3">
                    <input type="text" name="remote_host" placeholder="Remote Host" class="w-full border rounded p-2" required>
                    <input type="text" name="remote_port" value="22" placeholder="Port" class="w-full border rounded p-2">
                    <input type="text" name="remote_user" value="root" placeholder="User" class="w-full border rounded p-2">
                    <input type="text" name="username" placeholder="Username" class="w-full border rounded p-2" required>
                    <textarea name="remote_key" placeholder="SSH Key (optional, base64)" rows="3" class="w-full border rounded p-2"></textarea>
                </div>
                <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">Migrate</button>
            </form>
        </div>
    </div>
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-2">Migration Log</h2>
        <pre class="text-sm bg-gray-50 p-4 rounded max-h-64 overflow-auto">{{ $log }}</pre>
    </div>
</div>
@endsection