@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">PHP Info</h1>
    <div class="bg-white rounded-lg shadow p-6 overflow-auto">{!! $phpinfo !!}</div>
</div>
@endsection