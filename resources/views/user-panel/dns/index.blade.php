@extends('user-layouts.app')

@section('title', 'DNS Manager')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800">DNS Zones</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($domains as $domain)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $domain->domain }}</td>
                        <td class="px-6 py-4">
                            <a href="{{ route('user.dns.show', $domain->domain) }}" class="text-indigo-600 hover:text-indigo-800 text-sm"><i class="fas fa-edit mr-1"></i>Edit Zone</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-6 py-8 text-center text-gray-500">No domains found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
