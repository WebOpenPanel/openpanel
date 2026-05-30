@extends('user-layouts.app')

@section('title', 'Backups')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800">Account Backups</h3>
        <form method="POST" action="{{ route('user.backups.create') }}">
            @csrf
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                <i class="fas fa-download mr-2"></i>Create Backup
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Filename</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($backups as $backup)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $backup['name'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ number_format($backup['size'] / 1024 / 1024, 2) }} MB</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ date('Y-m-d H:i', $backup['date']) }}</td>
                            <td class="px-6 py-4 space-x-3">
                                <a href="{{ route('user.backups.download', ['file' => $backup['name']]) }}" class="text-blue-600 hover:text-blue-800 text-sm"><i class="fas fa-download mr-1"></i>Download</a>
                                <form method="POST" action="{{ route('user.backups.restore') }}" class="inline" onsubmit="return confirm('Restore this backup? Current files may be overwritten.')">
                                    @csrf
                                    <input type="hidden" name="file" value="{{ $backup['name'] }}">
                                    <button type="submit" class="text-green-600 hover:text-green-800 text-sm"><i class="fas fa-undo mr-1"></i>Restore</button>
                                </form>
                                <form method="POST" action="{{ route('user.backups.delete') }}" class="inline" onsubmit="return confirm('Delete this backup?')">
                                    @csrf
                                    <input type="hidden" name="file" value="{{ $backup['name'] }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash mr-1"></i>Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No backups found. Create one to get started.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
