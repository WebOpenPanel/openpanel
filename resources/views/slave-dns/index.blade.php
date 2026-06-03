@extends('layouts.app')
@section('title', 'Slave DNS Manager')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Add Slave Zone</h3>
        <form method="POST" action="{{ route('slave-dns.add') }}" class="flex gap-2">
            @csrf
            <input name="zone" placeholder="domain.com" class="border rounded px-3 py-1 flex-1" required>
            <input name="ip" placeholder="Master IP" class="border rounded px-3 py-1 w-40" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Add</button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Slave Zones</h3>
        @foreach($slaves as $z)
        <div class="flex items-center justify-between border-b py-2">
            <span class="text-sm">{{ $z }}</span>
            <form method="POST" action="{{ route('slave-dns.remove') }}">@csrf
                <input type="hidden" name="zone" value="{{ $z }}">
                <button class="text-red-600 text-sm">Remove</button>
            </form>
        </div>
        @endforeach
    </div>
</div>
@endsection
