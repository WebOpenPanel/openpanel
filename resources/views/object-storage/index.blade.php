@extends('layouts.app')
@section('title', 'Object Storage')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Object Storage</h1>
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('object-storage.save') }}">@csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium mb-1">Provider</label><select name="provider" class="w-full border rounded p-2"><option value="s3" {{ ($config['provider'] ?? '') === 's3' ? 'selected' : '' }}>Amazon S3</option><option value="minio" {{ ($config['provider'] ?? '') === 'minio' ? 'selected' : '' }}>MinIO</option><option value="other" {{ ($config['provider'] ?? '') === 'other' ? 'selected' : '' }}>Other S3-compatible</option></select></div>
                <div><label class="block text-sm font-medium mb-1">Endpoint</label><input type="text" name="endpoint" value="{{ $config['endpoint'] ?? '' }}" class="w-full border rounded p-2" placeholder="s3.amazonaws.com"></div>
                <div><label class="block text-sm font-medium mb-1">Access Key</label><input type="text" name="access_key" value="{{ $config['access_key'] ?? '' }}" class="w-full border rounded p-2"></div>
                <div><label class="block text-sm font-medium mb-1">Secret Key</label><input type="password" name="secret_key" value="{{ $config['secret_key'] ?? '' }}" class="w-full border rounded p-2"></div>
                <div><label class="block text-sm font-medium mb-1">Region</label><input type="text" name="region" value="{{ $config['region'] ?? 'us-east-1' }}" class="w-full border rounded p-2"></div>
                <div><label class="block text-sm font-medium mb-1">Bucket</label><input type="text" name="bucket" value="{{ $config['bucket'] ?? '' }}" class="w-full border rounded p-2"></div>
            </div>
            <div class="mt-4 flex gap-2">
                <button class="bg-blue-600 text-white px-4 py-2 rounded">Save Configuration</button>
                <form method="POST" action="{{ route('object-storage.test') }}">@csrf<button class="bg-green-600 text-white px-4 py-2 rounded">Test Connection</button></form>
            </div>
        </form>
    </div>
</div>
@endsection
