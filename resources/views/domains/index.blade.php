@extends('layouts.app')
@section('title', 'Domains')
@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search domains..." class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 w-64">
            <select name="type" class="py-2 px-3 border border-gray-300 rounded-lg text-sm">
                <option value="">All Types</option>
                <option value="main" {{ request('type')=='main'?'selected':'' }}>Main</option>
                <option value="addon" {{ request('type')=='addon'?'selected':'' }}>Addon</option>
                <option value="parked" {{ request('type')=='parked'?'selected':'' }}>Parked</option>
                <option value="subdomain" {{ request('type')=='subdomain'?'selected':'' }}>Subdomain</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Filter</button>
        </form>
        <a href="{{ route('domains.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-plus mr-2"></i> Add Domain</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Domain</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Account</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">SSL</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($domains as $domain)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $domain->domain }}</td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 text-xs bg-gray-100 rounded-full text-gray-700">{{ $domain->type }}</span></td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $domain->userAccount->domain ?? '-' }}</td>
                    <td class="px-5 py-3">@if($domain->ssl_enabled)<span class="text-green-500"><i class="fas fa-lock"></i></span>@else<span class="text-gray-300"><i class="fas fa-unlock"></i></span>@endif</td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" action="{{ route('domains.destroy', $domain) }}" onsubmit="return confirm('Delete this domain?')" class="inline">@csrf @method('DELETE')
                            <button class="p-2 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500"><i class="fas fa-globe text-gray-300 text-3xl mb-3"></i><p>No domains found.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $domains->withQueryString()->links() }}</div>
</div>
@endsection
