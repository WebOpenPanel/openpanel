@extends('layouts.app')
@section('title', 'DNS Zone: ' . $zone->domain)
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('dns.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-lg font-bold text-gray-900">DNS Zone: {{ $zone->domain }}</h2>
        <span class="text-xs text-gray-500">Serial: {{ $zone->serial }}</span>
    </div>

    <!-- Add Record -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Record</h3>
        <form method="POST" action="{{ route('dns.add-record', $zone) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div><label class="text-xs text-gray-500">Name</label>
                <input type="text" name="name" required placeholder="@" class="block w-32 px-2.5 py-2 border rounded-lg text-sm"></div>
            <div><label class="text-xs text-gray-500">Type</label>
                <select name="type" class="block w-24 px-2.5 py-2 border rounded-lg text-sm">
                    <option>A</option><option>AAAA</option><option>CNAME</option><option>MX</option><option>TXT</option><option>NS</option><option>SRV</option>
                </select></div>
            <div class="flex-1 min-w-[200px]"><label class="text-xs text-gray-500">Value</label>
                <input type="text" name="value" required placeholder="127.0.0.1" class="block w-full px-2.5 py-2 border rounded-lg text-sm"></div>
            <div><label class="text-xs text-gray-500">TTL</label>
                <input type="number" name="ttl" value="14400" class="block w-24 px-2.5 py-2 border rounded-lg text-sm"></div>
            <div><label class="text-xs text-gray-500">Priority</label>
                <input type="number" name="priority" class="block w-20 px-2.5 py-2 border rounded-lg text-sm"></div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Add</button>
        </form>
    </div>

    <!-- Records Table -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Value</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">TTL</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Priority</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($records as $record)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm font-mono text-gray-800">{{ $record->name }}</td>
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 text-xs bg-indigo-100 text-indigo-700 rounded-full font-medium">{{ $record->type }}</span></td>
                    <td class="px-5 py-2.5 text-sm font-mono text-gray-700 max-w-xs truncate">{{ $record->value }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $record->ttl }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $record->priority ?? '-' }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('dns.delete-record', $record) }}" onsubmit="return confirm('Delete this record?')" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-xs"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">No records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
