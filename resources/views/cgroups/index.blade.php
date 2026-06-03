@extends('layouts.app')
@section('title', 'CGroups Manager')
@section('content')
<div class="space-y-6">
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800">CGroups not installed.</p>
        <form method="POST" action="{{ route('cgroups.install') }}" class="mt-2">@csrf
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Install CGroups</button>
        </form>
    </div>
    @else
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Create CGroup</h3>
        <form method="POST" action="{{ route('cgroups.create') }}" class="space-y-2">
            @csrf
            <div class="flex gap-2">
                <input name="name" placeholder="Group name" class="border rounded px-3 py-1 flex-1" required>
                <input name="cpu_shares" placeholder="CPU shares (2-1024)" class="border rounded px-3 py-1 w-40">
                <input name="memory_limit" placeholder="Memory (e.g. 512M)" class="border rounded px-3 py-1 w-40">
                <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Create</button>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Groups</h3>
        @foreach($groups as $g)
        <div class="flex items-center justify-between border-b py-2">
            <span>{{ $g }}</span>
            <form method="POST" action="{{ route('cgroups.delete') }}">@csrf
                <input type="hidden" name="name" value="{{ $g }}">
                <button class="text-red-600 text-sm">Delete</button>
            </form>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
