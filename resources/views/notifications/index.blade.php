@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Notifications</h1>
    <div class="flex justify-between mb-4">
        <span>{{ $unreadCount }} unread</span>
        <form method="POST" action="{{ route('notifications.clear') }}">@csrf @method('DELETE')<button class="bg-red-600 text-white px-3 py-1 rounded text-sm">Clear All</button></form>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        @foreach($notifications as $n)<div class="border-b py-3 flex justify-between items-center {{ ($n['read_status'] ?? 0) ? 'opacity-60' : '' }}">
            <div><span class="inline-block px-2 py-0.5 rounded text-xs mr-2 {{ ($n['level'] ?? '') === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">{{ $n['level'] ?? 'info' }}</span> {{ $n['message'] }} <small class="text-gray-500">{{ $n['created_at'] }}</small></div>
            <div class="flex gap-2">
                @if(($n['read_status'] ?? 0) === 0)<form method="POST" action="{{ route('notifications.read', $n['id']) }}">@csrf<button class="text-blue-600 text-sm">Read</button></form>@endif
                <form method="POST" action="{{ route('notifications.destroy', $n['id']) }}">@csrf @method('DELETE')<button class="text-red-600 text-sm">Delete</button></form>
            </div>
        </div>@endforeach
        @if(empty($notifications))<p class="text-gray-500">No notifications.</p>@endif
    </div>
</div>
@endsection