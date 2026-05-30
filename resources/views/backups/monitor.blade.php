@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Backup Monitor</h1>
    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-2">Backup Log</h2>
            <pre class="text-sm bg-gray-900 text-green-400 p-4 rounded max-h-96 overflow-auto">{{ $log }}</pre>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-2">Restore Log</h2>
            <pre class="text-sm bg-gray-900 text-green-400 p-4 rounded max-h-96 overflow-auto">{{ $restoreLog }}</pre>
        </div>
    </div>
</div>
@endsection