@extends('layouts.app')
@section('title', 'Postfix List Manager')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Postfix List Manager</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($lists as $list)
        <a href="{{ route('postfix-lists.show', $list['name']) }}" class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <h3 class="font-bold text-lg">{{ ucfirst(str_replace('_', ' ', $list['name'])) }}</h3>
            <p class="text-sm text-gray-500">{{ $list['entries'] }} entries</p>
            <p class="text-xs text-gray-400 mt-1 font-mono">{{ $list['path'] }}</p>
        </a>
        @endforeach
    </div>
</div>
@endsection
