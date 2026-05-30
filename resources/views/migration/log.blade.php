@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Migration Log</h1>
    <pre class="bg-gray-900 text-green-400 p-4 rounded text-sm overflow-auto max-h-96">{{ $log }}</pre>
</div>
@endsection