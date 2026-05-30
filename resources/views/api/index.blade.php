@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">API Keys</h1>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="mb-4"><strong>Status:</strong> {{ $isActive ? 'Active' : 'Inactive' }}</p>
        @if(!empty($apiKey))<div class="mb-4"><label class="block text-sm font-medium">API Key</label><code class="block bg-gray-50 p-2 rounded mt-1 break-all">{{ $apiKey }}</code></div>@endif
        <div class="flex gap-2">
            <form method="POST" action="{{ route('api.generate') }}">@csrf<button class="bg-green-600 text-white px-4 py-2 rounded">Generate New Key</button></form>
            @if($isActive)<form method="POST" action="{{ route('api.destroy') }}">@csrf @method('DELETE')<button class="bg-red-600 text-white px-4 py-2 rounded">Delete Key</button></form>@endif
        </div>
    </div>
</div>
@endsection