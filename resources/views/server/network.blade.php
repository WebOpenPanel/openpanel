@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Network Info</h1>
    <div class="bg-white rounded-lg shadow p-6">
        <pre class="text-sm font-mono max-h-96 overflow-auto">{{ $network }}</pre>
    </div>
</div>
@endsection