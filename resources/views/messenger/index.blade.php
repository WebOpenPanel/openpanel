@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Messenger</h1>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="POST" action="{{ route('messenger.send') }}" class="flex gap-4">@csrf
            <input type="text" name="to" placeholder="To (username)" class="border rounded p-2 flex-1" required>
            <input type="text" name="message" placeholder="Message" class="border rounded p-2 flex-2" required>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Send</button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        @foreach($messages as $i => $msg)<div class="border-b py-3 {{ ($msg['read'] ?? false) ? 'opacity-60' : '' }}">
            <div class="flex justify-between"><strong>{{ $msg['from'] }}</strong><small>{{ $msg['time'] }}</small></div>
            <p>{{ $msg['message'] }}</p>
        </div>@endforeach
        @if(empty($messages))<p class="text-gray-500">No messages.</p>@endif
    </div>
</div>
@endsection