@extends('layouts.app')
@section('title', "Let's Encrypt")
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('ssl.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Certificates</a>
        <a href="{{ route('ssl.generate') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Generate</a>
        <a href="{{ route('ssl.letsencrypt') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Let's Encrypt</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-lock mr-2 text-green-500"></i>Issue Let's Encrypt Certificate</h3>
        <form method="POST" action="{{ route('ssl.letsencrypt-issue') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Domain</label><input type="text" name="domain" class="w-full px-3 py-2 border rounded-lg text-sm" required></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" name="email" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">Issue Certificate</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Renew Certificate</h3>
        <form method="POST" action="{{ route('ssl.letsencrypt-renew') }}" class="flex gap-3">
            @csrf
            <input type="text" name="domain" placeholder="Domain (blank = renew all)" class="flex-1 px-3 py-2 border rounded-lg text-sm">
            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Renew</button>
        </form>
    </div>
    @if(session('output'))
    <div class="bg-white rounded-xl shadow-sm border p-5"><pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ session('output') }}</pre></div>
    @endif
</div>
@endsection
