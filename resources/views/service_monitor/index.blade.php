@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Service Monitor</h1>
    <form method="POST" action="{{ route('service-monitor.save') }}" class="bg-white rounded-lg shadow p-6">@csrf
        <div class="mb-4"><label class="flex items-center"><input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }} class="mr-2"> Enable Service Monitor</label></div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div><label class="block text-sm font-medium">Alert Email</label><input type="email" name="email" value="root@localhost" class="mt-1 block w-full border rounded p-2"></div>
            <div><label class="block text-sm font-medium">Check Frequency (min)</label><input type="number" name="frequency" value="5" class="mt-1 block w-full border rounded p-2"></div>
        </div>
        <h2 class="text-lg font-semibold mb-2">Services to Monitor</h2>
        <div class="grid grid-cols-4 gap-2 mb-4 max-h-64 overflow-auto">
            @foreach($services as $svc)<label class="flex items-center text-sm"><input type="checkbox" name="services[]" value="{{ $svc['name'] }}" {{ $svc['monitored'] ? 'checked' : '' }} class="mr-1"> {{ $svc['name'] }}</label>@endforeach
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
    </form>
</div>
@endsection
