@extends('layouts.app')
@section('title', 'Web Server Templates')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Web Server Templates</h1>
    <div class="flex gap-2 mb-6">
        <a href="{{ route('webserver-templates.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">Create New Template</a>
    </div>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 border-b"><th class="text-left px-4 py-3">Name</th><th class="text-left px-4 py-3">Type</th><th class="text-left px-4 py-3">Modified</th><th class="text-right px-4 py-3">Actions</th></tr></thead>
            <tbody>
            @forelse($templates as $t)
            <tr class="border-b">
                <td class="px-4 py-2 font-mono">{{ $t['name'] }}</td>
                <td class="px-4 py-2"><span class="px-2 py-1 rounded text-xs {{ $t['type'] === 'builtin' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">{{ $t['type'] }}</span></td>
                <td class="px-4 py-2 text-xs">{{ date('Y-m-d H:i', $t['modified']) }}</td>
                <td class="px-4 py-2 text-right"><a href="{{ route('webserver-templates.edit', $t['name']) }}" class="text-blue-600 text-sm mr-2">Edit</a>@if($t['type'] === 'custom')<form method="POST" action="{{ route('webserver-templates.destroy', $t['name']) }}" class="inline">@csrf @method('DELETE')<button class="text-red-600 text-sm">Delete</button></form>@endif</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No templates found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
