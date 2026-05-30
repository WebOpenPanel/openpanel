@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Cgroups CPU Top</h1>
    <pre class="bg-white rounded-lg shadow p-6 text-sm max-h-96 overflow-auto">{{ $top }}</pre>
</div>
@endsection
