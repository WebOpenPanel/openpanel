@extends('user-layouts.app')

@section('title', 'Domain Aliases')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Domain Alias</h3>
        <form method="POST" action="{{ route('user.domains.alias.add') }}" class="flex flex-col md:flex-row gap-4">
            @csrf
            <input type="text" name="alias" placeholder="alias.com" pattern="[a-z0-9.\-]+" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
            <select name="domain" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                @foreach($domains as $d)
                    <option value="{{ $d }}">{{ $d }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Add Alias</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Domain Aliases</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alias</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Target Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($aliases as $alias)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $alias->alias }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $alias->domain }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs {{ $alias->isActive() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded-full">{{ $alias->status }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('user.domains.alias.remove') }}" class="inline" onsubmit="return confirm('Remove this alias?')">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $alias->id }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash mr-1"></i>Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No aliases found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection