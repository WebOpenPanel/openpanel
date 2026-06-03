@extends('layouts.app')
@section('title', 'Edit Logrotate: ' . $name)
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-edit mr-2"></i>Edit: {{ $name }}</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <form action="{{ route('logrotate.save') }}" method="POST">
        @csrf
        <input type="hidden" name="name" value="{{ $name }}">
        <textarea name="content" rows="20" class="w-full border rounded-lg p-4 font-mono text-sm">{{ $content }}</textarea>
        <div class="mt-4 flex gap-4">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Save</button>
        </div>
    </form>
    <form action="{{ route('logrotate.test') }}" method="POST">
        @csrf
        <input type="hidden" name="name" value="{{ $name }}">
        <button type="submit" class="mt-2 bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700">Test Config</button>
    </form>
    <a href="{{ route('logrotate.index') }}" class="text-indigo-600 hover:underline">Back to Logrotate</a>
</div>
@endsection
