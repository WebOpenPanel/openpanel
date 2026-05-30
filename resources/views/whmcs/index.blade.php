@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">WHMCS Integration</h1>
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('whmcs.save') }}">@csrf
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium">WHMCS URL</label><input type="text" name="whmcs_url" value="{{ $settings['whmcs_url'] ?? '' }}" class="mt-1 block w-full border rounded p-2"></div>
                <div><label class="block text-sm font-medium">API Username</label><input type="text" name="whmcs_api_user" value="{{ $settings['whmcs_api_user'] ?? '' }}" class="mt-1 block w-full border rounded p-2"></div>
                <div><label class="block text-sm font-medium">API Key</label><input type="password" name="whmcs_api_key" value="{{ $settings['whmcs_api_key'] ?? '' }}" class="mt-1 block w-full border rounded p-2"></div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
                <form method="POST" action="{{ route('whmcs.test') }}">@csrf<button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Test Connection</button></form>
            </div>
        </form>
    </div>
</div>
@endsection
