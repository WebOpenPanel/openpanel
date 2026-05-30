@extends('layouts.app')
@section('title', 'IP Manager')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('ip.index') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">IPs</a>
        <a href="{{ route('ip.nat') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">NAT</a>
        <a href="{{ route('ip.dns-resolvers') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">DNS</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-network-wired mr-2 text-indigo-500"></i>Add IP Address</h3>
        <form method="POST" action="{{ route('ip.add') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @csrf
            <input type="text" name="ip" placeholder="IP Address" class="px-3 py-2 border rounded-lg text-sm" required>
            <input type="text" name="netmask" value="255.255.255.0" placeholder="Netmask" class="px-3 py-2 border rounded-lg text-sm" required>
            <input type="text" name="interface" value="eth0" placeholder="Interface" class="px-3 py-2 border rounded-lg text-sm" required>
            <button class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Add IP</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Netmask</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Interface</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Owner</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Shared</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($ips as $ip)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-sm font-mono font-medium">{{ $ip['ip'] }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $ip['netmask'] }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $ip['interface'] }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $ip['owner'] }}</td>
                    <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs {{ $ip['is_shared'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">{{ $ip['is_shared'] ? 'Yes' : 'No' }}</span></td>
                    <td class="px-4 py-2.5">
                        <form method="POST" action="{{ route('ip.destroy') }}" class="inline" onsubmit="return confirm('Delete?')">@csrf<input type="hidden" name="ip" value="{{ $ip['ip'] }}"><button class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">Delete</button></form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
