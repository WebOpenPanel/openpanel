@extends('layouts.app')
@section('title', 'Domain Aliases')
@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div class="flex gap-2">
            <a href="{{ route('domains.index') }}" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Domains</a>
            <a href="{{ route('subdomains') }}" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Subdomains</a>
            <a href="{{ route('domain-aliases') }}" class="px-3 py-1.5 text-sm bg-indigo-100 text-indigo-700 rounded-lg">Aliases</a>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Alias</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Target Domain</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Account</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($aliases as $alias)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $alias->alias }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $alias->domain }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $alias->userAccount->domain ?? '-' }}</td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 text-xs {{ $alias->isActive() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded-full">{{ $alias->status }}</span></td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" action="{{ route('domain-aliases.destroy', $alias) }}" onsubmit="return confirm('Delete this alias?')" class="inline">@csrf @method('DELETE')
                            <button class="p-2 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500">No domain aliases found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $aliases->links() }}</div>
</div>
@endsection