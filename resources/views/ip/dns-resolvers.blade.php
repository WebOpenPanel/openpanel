@extends('layouts.app')
@section('title', 'DNS Resolvers')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('ip.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">IPs</a>
        <a href="{{ route('ip.dns-resolvers') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">DNS</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-satellite-dish mr-2 text-indigo-500"></i>DNS Resolvers</h3>
        <form method="POST" action="{{ route('ip.save-dns-resolvers') }}" class="space-y-3">
            @csrf
            @for($i = 0; $i < 4; $i++)
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Nameserver {{ $i + 1 }}</label><input type="text" name="servers[]" value="{{ $resolvers[$i] ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            @endfor
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save Resolvers</button>
        </form>
    </div>
</div>
@endsection
