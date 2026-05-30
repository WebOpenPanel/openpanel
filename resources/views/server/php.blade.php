@extends('layouts.app')
@section('title', 'PHP Manager')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('server.webserver') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Webserver</a>
        <a href="{{ route('server.php') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">PHP</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fab fa-php mr-2 text-indigo-500"></i>PHP Versions</h3>
        <div class="mb-3 text-sm text-gray-600">Default: <span class="font-mono font-bold">{{ $default }}</span></div>
        <table class="w-full mb-4">
            <thead class="border-b"><tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Version</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Binary</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Info</th>
            </tr></thead>
            <tbody class="divide-y">
                @foreach($versions as $v)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-medium">{{ $v['version'] }}</td>
                    <td class="px-4 py-2 text-sm font-mono text-gray-500">{{ $v['binary'] }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ Str::limit($v['info'], 80) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <form method="POST" action="{{ route('server.set-php-default') }}" class="flex gap-3">
            @csrf
            <select name="version" class="px-3 py-2 border rounded-lg text-sm">
                @foreach($versions as $v)
                <option value="{{ $v['version'] }}">{{ $v['version'] }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Set Default CLI</button>
        </form>
    </div>
</div>
@endsection
