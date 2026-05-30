@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Email Statistics</h1>
    @if(!$installed)<div class="bg-yellow-50 border border-yellow-200 rounded p-4">pflogsumm not installed. Install with: <code>yum -y install postfix-perl-scripts</code></div>@else
    <div class="mb-4"><strong>Queue:</strong> {{ $queueCount }} messages</div>
    <div class="bg-white rounded-lg shadow p-6"><pre class="text-sm max-h-96 overflow-auto">{{ $stats }}</pre></div>
    <div class="mt-4 flex gap-2">
        <a href="{{ route('email-stats.daily') }}" class="bg-blue-600 text-white px-4 py-2 rounded">Daily</a>
        <a href="{{ route('email-stats.weekly') }}" class="bg-blue-600 text-white px-4 py-2 rounded">Weekly</a>
        <form method="POST" action="{{ route('email-stats.flush-queue') }}">@csrf<button class="bg-yellow-600 text-white px-4 py-2 rounded">Flush Queue</button></form>
        <form method="POST" action="{{ route('email-stats.delete-queue') }}">@csrf<button class="bg-red-600 text-white px-4 py-2 rounded">Delete Queue</button></form>
    </div>@endif
</div>
@endsection
