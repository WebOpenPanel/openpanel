@extends('user-layouts.app')

@section('title', 'Subdomains')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800">Subdomains</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subdomain</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Path</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($subdomains as $sub)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $sub->subdomain }}.{{ $sub->domain }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $sub->path }}</td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('user.domains.subdomain.remove') }}" class="inline" onsubmit="return confirm('Remove this subdomain?')">
                                @csrf
                                <input type="hidden" name="id" value="{{ $sub->id }}">
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash mr-1"></i>Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">No subdomains found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
