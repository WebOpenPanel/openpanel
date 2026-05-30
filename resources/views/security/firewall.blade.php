@extends('layouts.app')
@section('title', 'Firewall')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.firewall') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Rules</a>
        <a href="{{ route('security.blocked-ips') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Blocked IPs</a>
        <a href="{{ route('security.allowed-ips') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Allowed IPs</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-shield-alt mr-2 text-red-500"></i>Add Firewall Rule</h3>
        <form method="POST" action="{{ route('security.add-firewall-rule') }}" class="space-y-3">
            @csrf
            <div class="flex flex-wrap gap-3">
                <input type="text" name="name" placeholder="Rule name" class="px-3 py-2 border rounded-lg text-sm w-40">
                <select name="action" class="px-3 py-2 border rounded-lg text-sm"><option value="allow">Allow</option><option value="deny">Deny</option><option value="drop">Drop</option><option value="reject">Reject</option></select>
                <select name="protocol" class="px-3 py-2 border rounded-lg text-sm"><option value="tcp">TCP</option><option value="udp">UDP</option><option value="icmp">ICMP</option><option value="all">All</option></select>
                <input type="text" name="source_ip" placeholder="Source IP" class="px-3 py-2 border rounded-lg text-sm w-36">
                <input type="text" name="source_port" placeholder="Source Port" class="px-3 py-2 border rounded-lg text-sm w-28">
                <input type="text" name="destination_ip" placeholder="Dest IP" class="px-3 py-2 border rounded-lg text-sm w-36">
                <input type="text" name="destination_port" placeholder="Dest Port" class="px-3 py-2 border rounded-lg text-sm w-28">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">Add Rule</button>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Protocol</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Source</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Destination</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rules as $rule)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-sm text-gray-800">{{ $rule->name ?? '-' }}</td>
                    <td class="px-4 py-2.5"><span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $rule->action=='allow'?'bg-green-100 text-green-800':'bg-red-100 text-red-800' }}">{{ $rule->action }}</span></td>
                    <td class="px-4 py-2.5 text-sm text-gray-600">{{ $rule->protocol }}</td>
                    <td class="px-4 py-2.5 text-sm font-mono text-gray-600">{{ $rule->source_ip ?? 'any' }}:{{ $rule->source_port ?? '*' }}</td>
                    <td class="px-4 py-2.5 text-sm font-mono text-gray-600">{{ $rule->destination_ip ?? 'any' }}:{{ $rule->destination_port ?? '*' }}</td>
                    <td class="px-4 py-2.5">
                        <form method="POST" action="{{ route('security.toggle-firewall-rule', $rule) }}" class="inline">@csrf
                            @if($rule->enabled)<button class="text-green-500"><i class="fas fa-toggle-on"></i></button>
                            @else<button class="text-gray-300"><i class="fas fa-toggle-off"></i></button>@endif
                        </form>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        <form method="POST" action="{{ route('security.delete-firewall-rule', $rule) }}" class="inline">@csrf @method('DELETE')
                            <button class="p-1 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-xs"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-gray-400">No firewall rules.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $rules->links() }}</div>
</div>
@endsection
