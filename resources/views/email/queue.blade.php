@extends('layouts.app')
@section('title', 'Mail Queue')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('email.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Accounts</a>
        <a href="{{ route('email.queue') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Queue</a>
        <a href="{{ route('email.dkim') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">DKIM</a>
        <a href="{{ route('email.mx') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">MX</a>
        <a href="{{ route('email.mail-log') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Log</a>
        <a href="{{ route('email.postfix-config') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Postfix</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700"><i class="fas fa-mail-bulk mr-2 text-indigo-500"></i>Mail Queue <span class="text-gray-400 font-normal">({{ $count }} items)</span></h3>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('email.queue-flush') }}" class="inline">@csrf<button class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">Flush</button></form>
                <form method="POST" action="{{ route('email.queue-delete') }}" class="inline" onsubmit="return confirm('Delete all queued mail?')">@csrf<button class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700">Delete All</button></form>
            </div>
        </div>
        <pre class="bg-gray-50 p-3 rounded text-xs overflow-auto max-h-96 font-mono">{{ $queue['raw'] ?? 'Mail queue is empty.' }}</pre>
    </div>
</div>
@endsection
