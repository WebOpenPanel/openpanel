@extends('layouts.app')
@section('title', 'MySQL Config')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('mysql.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Databases</a>
        <a href="{{ route('mysql.status') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Status</a>
        <a href="{{ route('mysql.processes') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Processes</a>
        <a href="{{ route('mysql.config') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Config</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-cog mr-2 text-indigo-500"></i>my.cnf</h3>
        <form method="POST" action="{{ route('mysql.save-config') }}" class="space-y-3">
            @csrf
            <textarea name="content" rows="25" class="w-full px-3 py-2 border rounded-lg text-sm font-mono">{{ $conf }}</textarea>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Save Configuration</button>
        </form>
    </div>
</div>
@endsection
