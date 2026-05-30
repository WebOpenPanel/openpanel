@extends('layouts.app')
@section('title', 'PECL Search Results')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">PECL Search Results</h1>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm"><thead><tr class="bg-gray-50 border-b"><th class="text-left px-4 py-3">Extension</th><th class="text-left px-4 py-3">Description</th><th class="text-right px-4 py-3"></th></tr></thead>
        <tbody>@forelse($results as $r)<tr class="border-b"><td class="px-4 py-2 font-mono">{{ $r['name'] }}</td><td class="px-4 py-2">{{ $r['description'] }}</td><td class="px-4 py-2 text-right"><form method="POST" action="{{ route('pecl.install') }}">@csrf<input type="hidden" name="extension" value="{{ $r['name'] }}"><button class="bg-green-600 text-white px-3 py-1 rounded text-sm">Install</button></form></td></tr>@empty<tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">No results.</td></tr>@endforelse</tbody></table>
    </div>
    <a href="{{ route('pecl.index') }}" class="inline-block mt-4 text-blue-600">Back to Extensions</a>
</div>
@endsection
