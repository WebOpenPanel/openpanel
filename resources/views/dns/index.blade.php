@extends('layouts.app')
@section('title', 'DNS Zones')
@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search zones..." class="px-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 w-64">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Search</button>
        </form>
        <a href="{{ route('dns.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-plus mr-2"></i> Add Zone</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Domain</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Records</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($zones as $zone)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-medium text-indigo-600">{{ $zone->domain }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $zone->records_count }} records</td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 text-xs {{ $zone->status=='active'?'bg-green-100 text-green-800':'bg-red-100 text-red-800' }} rounded-full">{{ $zone->status }}</span></td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('dns.show', $zone) }}" class="p-2 text-gray-400 hover:text-indigo-600"><i class="fas fa-eye text-sm"></i></a>
                        <form method="POST" action="{{ route('dns.destroy', $zone) }}" onsubmit="return confirm('Delete zone?')" class="inline">@csrf @method('DELETE')
                            <button class="p-2 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-gray-500">No DNS zones found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $zones->withQueryString()->links() }}</div>
</div>
@endsection
