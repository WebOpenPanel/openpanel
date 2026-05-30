@extends('layouts.app')
@section('title', 'Disk Usage')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('files.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Files</a>
        <a href="{{ route('files.disk-usage') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Disk Usage</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-hdd mr-2 text-indigo-500"></i>Disk Partitions</h3>
        <table class="w-full mb-4">
            <thead class="border-b"><tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Filesystem</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Size</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Used</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Avail</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Use%</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Mount</th>
            </tr></thead>
            <tbody class="divide-y">
                @foreach($details as $d)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-mono">{{ $d['filesystem'] }}</td>
                    <td class="px-4 py-2 text-sm">{{ $d['size'] }}</td>
                    <td class="px-4 py-2 text-sm">{{ $d['used'] }}</td>
                    <td class="px-4 py-2 text-sm">{{ $d['available'] }}</td>
                    <td class="px-4 py-2 text-sm"><span class="px-2 py-0.5 rounded-full text-xs {{ (int)$d['percent'] > 90 ? 'bg-red-100 text-red-700' : ((int)$d['percent'] > 70 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">{{ $d['percent'] }}</span></td>
                    <td class="px-4 py-2 text-sm font-mono">{{ $d['mount'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Largest Directories in /home</h3>
        <div class="space-y-2">
            @foreach($usage as $u)
            <div class="flex items-center gap-3">
                <span class="text-sm font-mono text-gray-500 w-16 text-right">{{ $u['size'] }}</span>
                <div class="flex-1 bg-gray-200 rounded-full h-4 overflow-hidden"><div class="bg-indigo-500 h-full rounded-full" @style(['width:'.min(100, max(5, (int)$u['size'])).'%'])></div></div>
                <span class="text-sm text-gray-700">{{ $u['path'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
