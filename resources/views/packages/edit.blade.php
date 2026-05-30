@extends('layouts.app')
@section('title', 'Edit Package - ' . $package->name)
@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('packages.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-lg font-bold">Edit: {{ $package->name }}</h2>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('packages.update', $package) }}" class="space-y-5">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Disk Space (MB)</label>
                    <input type="number" name="disk_space_mb" value="{{ $package->disk_space_mb }}" required class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Bandwidth (MB)</label>
                    <input type="number" name="bandwidth_mb" value="{{ $package->bandwidth_mb }}" required class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Domains</label>
                    <input type="number" name="max_domains" value="{{ $package->max_domains }}" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Emails</label>
                    <input type="number" name="max_email_accounts" value="{{ $package->max_email_accounts }}" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Databases</label>
                    <input type="number" name="max_databases" value="{{ $package->max_databases }}" class="w-full px-3 py-2.5 border rounded-lg text-sm"></div>
            </div>
            <div class="flex items-center gap-3 pt-3 border-t">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-save mr-2"></i> Update</button>
                <a href="{{ route('packages.show', $package) }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
