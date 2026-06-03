@extends('layouts.app')
@section('title', 'Edit FreeDNS Zone: ' . $domain)
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Editing Zone: {{ $domain }}</h1>
        <a href="{{ route('freedns.index') }}" class="text-indigo-600 hover:underline">← Back</a>
    </div>
    <form action="{{ route('freedns.save') }}" method="POST" class="bg-white rounded-lg shadow p-6">
        @csrf
        <input type="hidden" name="domain" value="{{ $domain }}">
        <textarea name="content" rows="20" class="w-full font-mono text-sm border rounded-lg p-4">{{ $content }}</textarea>
        <div class="mt-4"><button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Save Zone</button></div>
    </form>
</div>
@endsection