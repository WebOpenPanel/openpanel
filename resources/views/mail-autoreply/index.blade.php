@extends('layouts.app')
@section('title', 'Mail Auto-Reply')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Create Auto-Reply</h3>
        <form method="POST" action="{{ route('mail-autoreply.store') }}" class="space-y-3">
            @csrf
            <input name="email" type="email" placeholder="user@domain.com" class="w-full border rounded px-3 py-2" required>
            <input name="subject" placeholder="Subject" class="w-full border rounded px-3 py-2" required>
            <textarea name="body" rows="6" placeholder="Auto-reply message..." class="w-full border rounded px-3 py-2" required></textarea>
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Create Auto-Reply</button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Active Auto-Replies</h3>
        @forelse($replies as $r)
        <div class="flex items-center justify-between border-b py-2">
            <span class="text-sm">{{ $r['user'] }}</span>
            <form method="POST" action="{{ route('mail-autoreply.destroy') }}">@csrf
                <input type="hidden" name="email" value="{{ $r['user'] }}">
                <button class="text-red-600 text-sm">Remove</button>
            </form>
        </div>
        @empty
        <p class="text-gray-500 text-sm">No auto-replies configured.</p>
        @endforelse
    </div>
</div>
@endsection
