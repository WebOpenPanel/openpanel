@extends('layouts.app')
@section('title', 'Subdomains')
@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div class="flex gap-2">
            <a href="{{ route('domains.index') }}" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Domains</a>
            <a href="{{ route('subdomains') }}" class="px-3 py-1.5 text-sm bg-indigo-100 text-indigo-700 rounded-lg">Subdomains</a>
            <a href="{{ route('domain-aliases') }}" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Aliases</a>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Full Domain</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Parent</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Account</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Document Root</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($subdomains as $sub)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $sub->fullDomain() }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $sub->domain }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600">{{ $sub->userAccount->domain ?? '-' }}</td>
                    <td class="px-5 py-3 text-sm text-gray-500 font-mono text-xs">{{ $sub->document_root }}</td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 text-xs {{ $sub->isActive() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded-full">{{ $sub->status }}</span></td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" action="{{ route('subdomains.destroy', $sub) }}" onsubmit="return confirm('Delete this subdomain?')" class="inline">@csrf @method('DELETE')
                            <button class="p-2 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">No subdomains found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $subdomains->links() }}</div>
</div>
@endsection