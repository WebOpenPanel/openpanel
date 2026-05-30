@extends('layouts.app')
@section('title', 'Postfix List - ' . ucfirst($type))
@section('content')
<div class="p-6">
    <div class="flex items-center gap-2 mb-6"><a href="{{ route('postfix-lists.index') }}" class="text-blue-600">Postfix Lists</a> <span>/</span><h1 class="text-2xl font-bold">{{ ucfirst(str_replace('_', ' ', $type)) }}</h1></div>
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('postfix-lists.add') }}" class="flex gap-2 mb-4">@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="text" name="pattern" placeholder="user@example.com or 192.168.1.0/24" class="border rounded p-2 flex-1" required><select name="action" class="border rounded p-2">@foreach($actions as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach</select><button class="bg-blue-600 text-white px-4 py-2 rounded">Add</button></form>
        <table class="w-full text-sm"><thead><tr class="border-b"><th class="text-left py-2">Pattern</th><th class="text-left py-2">Action</th><th class="text-right py-2"></th></tr></thead>
        <tbody>@forelse($entries as $e)<tr class="border-b"><td class="py-2 font-mono">{{ $e['pattern'] }}</td><td class="py-2">{{ $e['action'] }}</td><td class="py-2 text-right"><form method="POST" action="{{ route('postfix-lists.remove') }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="pattern" value="{{ $e['pattern'] }}"><button class="text-red-600 text-sm">Delete</button></form></td></tr>@empty<tr><td colspan="3" class="py-4 text-center text-gray-400">No entries.</td></tr>@endforelse</tbody></table>
    </div>
</div>
@endsection
