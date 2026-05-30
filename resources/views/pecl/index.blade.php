@extends('layouts.app')
@section('title', 'PHP PECL Extensions')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">PHP PECL Extensions</h1>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex gap-4 mb-4">
            @foreach($phpVersions as $v)
            <a href="{{ route('pecl.index', ['php' => $v['version']]) }}" class="px-3 py-1 rounded {{ $selectedPhp === $v['version'] ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">PHP {{ $v['version'] }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('pecl.search') }}" class="mb-4 flex gap-2">
            <input type="text" name="query" placeholder="Search extensions..." class="border rounded p-2 flex-1">
            <input type="hidden" name="php" value="{{ $selectedPhp }}">
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Search</button>
        </form>
        <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="text-left py-2">Extension</th><th class="text-left py-2">Status</th><th class="text-right py-2">Actions</th></tr></thead>
            <tbody>
            @foreach($installed as $ext)
            <tr class="border-b">
                <td class="py-2 font-mono">{{ $ext['name'] }}</td>
                <td class="py-2"><span class="px-2 py-1 rounded text-xs {{ $ext['active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $ext['active'] ? 'Active' : 'Inactive' }}</span></td>
                <td class="py-2 text-right">
                    <form method="POST" action="{{ route('pecl.toggle') }}" class="inline">@csrf<input type="hidden" name="extension" value="{{ $ext['name'] }}"><input type="hidden" name="php" value="{{ $selectedPhp }}"><input type="hidden" name="action" value="{{ $ext['active'] ? 'disable' : 'enable' }}"><button class="text-{{ $ext['active'] ? 'yellow' : 'green' }}-600 text-sm mr-2">{{ $ext['active'] ? 'Disable' : 'Enable' }}</button></form>
                    <form method="POST" action="{{ route('pecl.uninstall') }}" class="inline">@csrf<input type="hidden" name="extension" value="{{ $ext['name'] }}"><input type="hidden" name="php" value="{{ $selectedPhp }}"><button class="text-red-600 text-sm">Uninstall</button></form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
