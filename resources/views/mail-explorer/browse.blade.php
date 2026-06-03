@extends('layouts.app')
@section('title', 'Mail Explorer - Browse')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-envelope-open mr-2"></i>Mail: {{ $domain }}{{ $user ? " ({$user})" : '' }}</h1>
        <a href="{{ route('mail-explorer.index') }}" class="text-indigo-600 hover:underline">← Back</a>
    </div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modified</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th></tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($files as $file)
                <tr><td class="px-6 py-4 text-sm text-gray-800">{{ $file['name'] }}</td><td class="px-6 py-4 text-sm text-gray-500">{{ number_format($file['size'] / 1024, 1) }} KB</td><td class="px-6 py-4 text-sm text-gray-500">{{ $file['modified'] }}</td><td class="px-6 py-4"><a href="{{ route('mail-explorer.file', ['path' => $file['path']]) }}" class="text-indigo-600 hover:underline text-sm">View</a></td></tr>
                @empty
                <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No mail files found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection