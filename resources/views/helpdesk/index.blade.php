@extends('layouts.app')
@section('title', 'Help Desk')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Help Desk Wizard</h1>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Available Software</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            @foreach($software as $sw)
            <div class="border rounded p-4 {{ ($config['software'] ?? '') === $sw['name'] ? 'border-blue-500 bg-blue-50' : '' }}">
                <h3 class="font-bold">{{ $sw['name'] }}</h3>
                <p class="text-sm text-gray-600 mb-3">{{ $sw['description'] }}</p>
                <form method="POST" action="{{ route('helpdesk.install') }}">@csrf<input type="hidden" name="software" value="{{ $sw['name'] }}"><button class="bg-green-600 text-white px-3 py-1 rounded text-sm">Install</button></form>
            </div>
            @endforeach
        </div>
        <h2 class="text-lg font-semibold mb-4">Configuration</h2>
        <form method="POST" action="{{ route('helpdesk.save') }}">@csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium mb-1">Software</label><select name="software" class="w-full border rounded p-2">@foreach($software as $sw)<option value="{{ $sw['name'] }}" {{ ($config['software'] ?? '') === $sw['name'] ? 'selected' : '' }}>{{ $sw['name'] }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium mb-1">Domain</label><input type="text" name="domain" value="{{ $config['domain'] ?? '' }}" class="w-full border rounded p-2" placeholder="help.yourdomain.com"></div>
                <div><label class="block text-sm font-medium mb-1">Admin Email</label><input type="email" name="admin_email" value="{{ $config['admin_email'] ?? '' }}" class="w-full border rounded p-2"></div>
            </div>
            <button class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">Save Configuration</button>
        </form>
    </div>
</div>
@endsection
