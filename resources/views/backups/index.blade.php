@extends('layouts.app')
@section('title', 'Backups')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <a href="{{ route('backups.config') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200"><i class="fas fa-cog mr-1"></i> Backup Config</a>
        <form method="POST" action="{{ route('backups.generate') }}" class="inline">@csrf
            <input type="hidden" name="type" value="full">
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-download mr-2"></i> Generate Backup</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Filename</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Size</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($backups as $backup)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm font-mono text-gray-800">{{ $backup->filename }}</td>
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 text-xs bg-gray-100 rounded-full">{{ $backup->type }}</span></td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $backup->size_formatted }}</td>
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 text-xs {{ $backup->status=='completed'?'bg-green-100 text-green-800':'bg-yellow-100 text-yellow-800' }} rounded-full">{{ $backup->status }}</span></td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $backup->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('backups.destroy', $backup) }}" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">No backups found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $backups->links() }}</div>
</div>
@endsection
