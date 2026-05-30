@extends('user-layouts.app')

@section('title', 'Autoresponders')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Autoresponders</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($autoresponders as $ar)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-800">{{ $ar->email }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $ar->subject }}</td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('user.email.autoresponder.delete') }}" class="inline" onsubmit="return confirm('Delete this autoresponder?')">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $ar->id }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash mr-1"></i>Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">No autoresponders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Autoresponder</h3>
        <form method="POST" action="{{ route('user.email.autoresponder.create') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="text" name="email" placeholder="email@domain.com" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                <input type="text" name="subject" placeholder="Subject" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
            </div>
            <textarea name="body" rows="4" placeholder="Auto-reply message body..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required></textarea>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Create Autoresponder</button>
        </form>
    </div>
</div>
@endsection
