@extends('layouts.app')
@section('title', 'Restore Backup')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-undo mr-2"></i>Restore Backup</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Upload Backup</h2>
        <form action="{{ route('restore-backup.upload') }}" method="POST" enctype="multipart/form-data" class="flex gap-4 items-end">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">Backup File (.tar.gz, max 5MB)</label>
                <input type="file" name="backup_file" accept=".tar.gz,.tgz" class="w-full border rounded-lg px-3 py-2 mt-1" required>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Upload</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold">Available Backups</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Restore To</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($files as $file)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono text-gray-800">{{ $file['name'] }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ number_format($file['size'] / 1024 / 1024, 2) }} MB</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $file['modified'] }}</td>
                    <td class="px-6 py-4 text-right">
                        <form action="{{ route('restore-backup.restore') }}" method="POST" class="inline-flex gap-2 items-center" onsubmit="return confirm('Restore this backup? This will overwrite existing files.')">
                            @csrf
                            <input type="hidden" name="file" value="{{ $file['name'] }}">
                            <input type="text" name="username" placeholder="username" class="border rounded px-2 py-1 text-sm w-32" required>
                            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">Restore</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No backup files found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
