@extends('layouts.app')
@section('title', 'Config File Editor')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Config File Editor</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>@endif
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('config-editor.load') }}" method="POST" class="flex gap-4 items-end">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Config File</label>
                <select name="file" class="w-full border rounded-lg px-3 py-2">
                    @foreach($allowedFiles as $f)
                        <option value="{{ $f }}">{{ $f }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Open</button>
        </form>
    </div>
</div>
@endsection
