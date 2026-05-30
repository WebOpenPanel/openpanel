@extends('layouts.app')
@section('title', 'MySQL Processes')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('mysql.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Databases</a>
        <a href="{{ route('mysql.status') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Status</a>
        <a href="{{ route('mysql.processes') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Processes</a>
        <a href="{{ route('mysql.config') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Config</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Host</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">DB</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Command</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Time</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">State</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Info</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($processes as $proc)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-mono">{{ $proc->Id ?? '' }}</td>
                    <td class="px-4 py-2 text-sm">{{ $proc->User ?? '' }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ $proc->Host ?? '' }}</td>
                    <td class="px-4 py-2 text-sm">{{ $proc->db ?? '' }}</td>
                    <td class="px-4 py-2 text-sm">{{ $proc->Command ?? '' }}</td>
                    <td class="px-4 py-2 text-sm">{{ $proc->Time ?? '' }}s</td>
                    <td class="px-4 py-2 text-sm">{{ $proc->State ?? '' }}</td>
                    <td class="px-4 py-2 text-xs text-gray-500 max-w-xs truncate">{{ Str::limit($proc->Info ?? '', 80) }}</td>
                    <td class="px-4 py-2">
                        <form method="POST" action="{{ route('mysql.kill-process', $proc->Id ?? 0) }}" class="inline" onsubmit="return confirm('Kill this process?')">
                            @csrf
                            <button class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-times"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No active processes</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
