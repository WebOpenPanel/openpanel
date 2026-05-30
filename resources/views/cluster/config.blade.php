@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Cluster Config</h1>
    <form method="POST" action="{{ route('cluster.save-config') }}" class="bg-white rounded-lg shadow p-6">@csrf
        @foreach($config as $key => $value)<div class="mb-4"><label class="block text-sm font-medium">{{ $key }}</label><input type="text" name="{{ $key }}" value="{{ $value }}" class="mt-1 block w-full border rounded p-2"></div>@endforeach
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
    </form>
</div>
@endsection
