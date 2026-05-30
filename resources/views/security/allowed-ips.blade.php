@extends('layouts.app')
@section('title', 'Allowed IPs')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.firewall') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Rules</a>
        <a href="{{ route('security.blocked-ips') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Blocked IPs</a>
        <a href="{{ route('security.allowed-ips') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Allowed IPs</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <form method="POST" action="{{ route('security.allow-ip') }}" class="flex gap-3">
            @csrf
            <input type="text" name="ip_address" required placeholder="IP Address" class="px-3 py-2 border rounded-lg text-sm w-44">
            <input type="text" name="description" placeholder="Description" class="flex-1 px-3 py-2 border rounded-lg text-sm">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700"><i class="fas fa-check mr-1"></i>Allow IP</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Added By</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($allowedIps as $ip)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm font-mono text-gray-800">{{ $ip->ip_address }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $ip->description ?? '-' }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $ip->added_by ?? '-' }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('security.remove-allowed-ip', $ip) }}" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No allowed IPs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $allowedIps->links() }}</div>
</div>
@endsection
