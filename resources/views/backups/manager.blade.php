@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Backup Manager</h1>
    <div class="flex justify-end mb-4"><a href="{{ route('backups.manager-monitor') }}" class="bg-gray-600 text-white px-4 py-2 rounded">Monitor Logs</a></div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full"><thead><tr class="bg-gray-50 border-b"><th class="text-left p-3">ID</th><th class="text-left p-3">Type</th><th class="text-left p-3">Compression</th><th class="text-left p-3">Status</th><th class="text-left p-3">Last Exec</th><th class="text-left p-3">Actions</th></tr></thead>
        <tbody>@foreach($backups as $b)<tr class="border-b hover:bg-gray-50"><td class="p-3">{{ $b['id'] }}</td><td class="p-3">{{ $b['type'] }}</td><td class="p-3">{{ $b['compression'] }}</td>
            <td class="p-3"><span class="px-2 py-0.5 rounded text-xs {{ $b['status'] === 'enabled' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $b['status'] }}</span></td>
            <td class="p-3">{{ $b['last_exec'] }}</td>
            <td class="p-3 flex gap-2">
                <a href="{{ route('backups.manager-edit', $b['id']) }}" class="text-blue-600 text-sm">Edit</a>
                <form method="POST" action="{{ route('backups.manager-run', $b['id']) }}">@csrf<button class="text-green-600 text-sm">Run</button></form>
                <form method="POST" action="{{ route('backups.manager-toggle', $b['id']) }}">@csrf<input type="hidden" name="status" value="{{ $b['status'] === 'enabled' ? 0 : 1 }}"><button class="text-yellow-600 text-sm">{{ $b['status'] === 'enabled' ? 'Disable' : 'Enable' }}</button></form>
                <form method="POST" action="{{ route('backups.manager-delete', $b['id']) }}">@csrf @method('DELETE')<button class="text-red-600 text-sm">Delete</button></form>
            </td>
        </tr>@endforeach
        @if(empty($backups))<tr><td colspan="6" class="p-3 text-center text-gray-500">No backup configurations.</td></tr>@endif
        </tbody></table>
    </div>
</div>
@endsection