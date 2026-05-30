@extends('user-layouts.app')

@section('title', 'Email Forwarders')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Forwarders</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($forwarders as $fwd)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-800">{{ $fwd->source }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $fwd->destination }}</td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('user.email.forwarder.delete') }}" class="inline" onsubmit="return confirm('Delete this forwarder?')">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $fwd->id }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash mr-1"></i>Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">No forwarders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Forwarder</h3>
        <form method="POST" action="{{ route('user.email.forwarder.create') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <input type="text" name="source" placeholder="user@domain.com" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
            <input type="email" name="destination" placeholder="destination@email.com" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Add Forwarder</button>
        </form>
    </div>
</div>
@endsection
