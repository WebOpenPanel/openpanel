@extends('layouts.app')
@section('title', 'MX Routing')
@section('content')
<div class="space-y-6">
    @foreach($domains as $domain)
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-2">{{ $domain }}</h3>
        <p class="text-sm text-gray-600 mb-2">Current: {{ $current[$domain] ?? 'local' }}</p>
        <form method="POST" action="{{ route('mx-routing.update') }}" class="flex gap-2 items-center">
            @csrf
            <input type="hidden" name="domain" value="{{ $domain }}">
            <select name="routing" class="border rounded px-2 py-1 text-sm">
                <option value="local">Local</option>
                <option value="remote">Remote</option>
                <option value="backup">Backup</option>
            </select>
            <input name="mx_host" placeholder="MX Host" class="border rounded px-2 py-1 text-sm w-48">
            <input name="priority" placeholder="Priority" value="10" class="border rounded px-2 py-1 text-sm w-20">
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Save</button>
        </form>
    </div>
    @endforeach
</div>
@endsection
