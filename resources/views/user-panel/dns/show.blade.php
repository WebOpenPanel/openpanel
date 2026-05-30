@extends('user-layouts.app')

@section('title', 'DNS Zone: ' . $domain)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800">DNS Zone: {{ $domain }}</h3>
        <a href="{{ route('user.dns.index') }}" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">TTL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($records as $i => $record)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-sm font-mono text-gray-800">{{ $record['name'] }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ $record['ttl'] }}</td>
                            <td class="px-6 py-3"><span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full">{{ $record['type'] }}</span></td>
                            <td class="px-6 py-3 text-sm font-mono text-gray-600 truncate max-w-xs">{{ $record['value'] }}</td>
                            <td class="px-6 py-3">
                                <form method="POST" action="{{ route('user.dns.record.delete') }}" onsubmit="return confirm('Delete this record?')">
                                    @csrf
                                    <input type="hidden" name="domain" value="{{ $domain }}">
                                    <input type="hidden" name="line_number" value="{{ $i }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add DNS Record</h3>
        <form method="POST" action="{{ route('user.dns.record.add') }}" class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @csrf
            <input type="hidden" name="domain" value="{{ $domain }}">
            <input type="text" name="name" placeholder="www" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm" required>
            <select name="type" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                <option value="A">A</option>
                <option value="AAAA">AAAA</option>
                <option value="CNAME">CNAME</option>
                <option value="MX">MX</option>
                <option value="TXT">TXT</option>
                <option value="NS">NS</option>
            </select>
            <input type="text" name="value" placeholder="Value" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm" required>
            <input type="number" name="ttl" placeholder="TTL" value="14400" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">Add Record</button>
        </form>
    </div>
</div>
@endsection
