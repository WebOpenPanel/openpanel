@extends('layouts.app')
@section('title', 'Blocked IPs')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.firewall') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Rules</a>
        <a href="{{ route('security.blocked-ips') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Blocked IPs</a>
        <a href="{{ route('security.allowed-ips') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Allowed IPs</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <form method="POST" action="{{ route('security.block-ip') }}" class="flex gap-3">
            @csrf
            <input type="text" name="ip_address" required placeholder="IP Address" class="px-3 py-2 border rounded-lg text-sm w-44">
            <input type="text" name="reason" placeholder="Reason" class="flex-1 px-3 py-2 border rounded-lg text-sm">
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700"><i class="fas fa-ban mr-1"></i>Block IP</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Reason</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Added By</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($blockedIps as $ip)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm font-mono text-gray-800">{{ $ip->ip_address }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $ip->reason ?? '-' }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $ip->added_by ?? '-' }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('security.unblock-ip', $ip) }}" class="inline">@csrf @method('DELETE')
                            <button class="px-3 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200"><i class="fas fa-unlock mr-1"></i>Unblock</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No blocked IPs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $blockedIps->links() }}</div>
</div>
@endsection
