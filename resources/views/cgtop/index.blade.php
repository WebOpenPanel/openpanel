@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Cgroups Top Monitor</h1>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-2">Status</h2><pre class="text-sm bg-gray-50 p-4 rounded">{{ $status }}</pre>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-2">Top</h2><pre class="text-sm bg-gray-50 p-4 rounded max-h-96 overflow-auto">{{ $top }}</pre>
    </div>
    <form method="POST" action="{{ route('cgtop.restart') }}" class="mt-4">@csrf<button class="bg-blue-600 text-white px-4 py-2 rounded">Restart Cgroups</button></form>
</div>
@endsection
