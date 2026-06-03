@extends('layouts.app')
@section('title', 'Disk Usage')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('server.hostname') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Hostname</a>
        <a href="{{ route('server.processes') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Processes</a>
        <a href="{{ route('server.network') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Network</a>
        <a href="{{ route('server.disk') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Disk</a>
        <a href="{{ route('server.time') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Time</a>
        <a href="{{ route('server.yum') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Packages</a>
        <a href="{{ route('server.webserver') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Webserver</a>
        <a href="{{ route('server.php') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">PHP</a>
        <a href="{{ route('server.terminal') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Terminal</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b"><h3 class="text-sm font-semibold text-gray-700">Disk Usage</h3></div>
        @if(is_array($disks) && count($disks) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Filesystem</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Used</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Available</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Use%</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mount</th>
                </tr></thead>
                <tbody class="divide-y">
                    @foreach($disks as $d)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-xs">{{ $d['filesystem'] ?? '' }}</td>
                        <td class="px-4 py-2">{{ isset($d['size']) ? round($d['size']/1073741824, 1) . 'G' : '' }}</td>
                        <td class="px-4 py-2">{{ isset($d['used']) ? round($d['used']/1073741824, 1) . 'G' : '' }}</td>
                        <td class="px-4 py-2">{{ isset($d['available']) ? round($d['available']/1073741824, 1) . 'G' : '' }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5"><div class="h-1.5 rounded-full {{ ($d['percent'] ?? 0) > 90 ? 'bg-red-500' : (($d['percent'] ?? 0) > 70 ? 'bg-yellow-500' : 'bg-green-500') }}" style="width:{{ $d['percent'] ?? 0 }}%"></div></div>
                                <span class="text-xs">{{ $d['percent'] ?? 0 }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $d['mount'] ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <pre class="p-5 text-sm font-mono">{{ print_r($disks, true) }}</pre>
        @endif
    </div>
</div>
@endsection
