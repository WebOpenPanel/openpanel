@extends('layouts.app')
@section('title', 'PostgreSQL')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('mysql.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Databases</a>
        <a href="{{ route('mysql.postgresql') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">PostgreSQL</a>
        <a href="{{ route('mysql.mongodb') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">MongoDB</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-database mr-2 text-blue-500"></i>PostgreSQL</h3>
        @if($installed)
            <div class="mb-3"><span class="px-3 py-1 rounded-full text-sm font-medium {{ ($status['active'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ ($status['active'] ?? false) ? 'Running' : 'Stopped' }}</span></div>
            <pre class="bg-gray-50 p-3 rounded text-xs overflow-auto max-h-64">{{ $databases['raw'] ?? '' }}</pre>
        @else
            <div class="text-center py-8"><i class="fas fa-info-circle text-3xl text-gray-300 mb-2"></i><p class="text-gray-500">PostgreSQL is not installed on this server.</p></div>
        @endif
    </div>
</div>
@endsection
