@extends('layouts.app')
@section('title', 'MX Routing')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('email.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Accounts</a>
        <a href="{{ route('email.dkim') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">DKIM</a>
        <a href="{{ route('email.mx') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">MX</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-route mr-2 text-indigo-500"></i>MX Entry</h3>
        <form method="POST" action="{{ route('email.save-mx') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Domain</label><input type="text" name="domain" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Priority</label><input type="number" name="priority" value="10" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Mail Server</label><input type="text" name="exchange" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Set MX Record</button>
        </form>
    </div>
</div>
@endsection
