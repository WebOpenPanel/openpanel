@extends('layouts.app')
@section('title', 'DKIM Manager')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('email.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Accounts</a>
        <a href="{{ route('email.queue') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Queue</a>
        <a href="{{ route('email.dkim') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">DKIM</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-key mr-2 text-indigo-500"></i>DKIM Management</h3>
        <p class="text-sm text-gray-500 mb-4">DKIM keys are managed through the DNS zone system. Add DKIM for individual domains or all at once.</p>
        <div class="flex gap-3">
            <form method="POST" action="{{ route('dns.add-dkim-all') }}">@csrf<button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Add DKIM for All Domains</button></form>
        </div>
    </div>
</div>
@endsection
