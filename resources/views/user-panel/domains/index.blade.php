@extends('user-layouts.app')

@section('title', 'My Domains')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">My Domains</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document Root</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SSL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($domains as $domain)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-800">
                                <a href="http://{{ $domain->domain }}" target="_blank" class="text-indigo-600 hover:underline">{{ $domain->domain }}</a>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $domain->path ?? '/home/' . auth()->user()->username . '/web/' . $domain->domain . '/public_html' }}</td>
                            <td class="px-6 py-4">
                                @if($domain->ssl_enabled ?? false)
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-500 rounded-full">None</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $domain->created_at ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No domains found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Subdomain</h3>
        <form method="POST" action="{{ route('user.domains.subdomain.add') }}" class="flex flex-col md:flex-row gap-4">
            @csrf
            <input type="text" name="subdomain" placeholder="subdomain" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
            <select name="domain" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                @foreach($domains as $d)
                    <option value="{{ $d->domain }}">{{ $d->domain }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Add Subdomain</button>
        </form>
    </div>
</div>
@endsection
