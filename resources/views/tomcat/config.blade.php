@extends('layouts.app')
@section('title', 'Tomcat Config')
@section('content')
<div class="space-y-4">
    <h2 class="text-lg font-semibold">Tomcat server.xml</h2>
    <form method="POST" action="{{ route('tomcat.save-config') }}">
        @csrf
        <textarea name="config" rows="30" class="w-full font-mono text-sm border rounded p-3">{{ $config }}</textarea>
        <button class="mt-2 bg-blue-600 text-white px-4 py-2 rounded">Save & Restart</button>
    </form>
</div>
@endsection
