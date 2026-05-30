@extends('layouts.app')
@section('title', 'Edit Account - ' . $account->domain)

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('accounts.show', $account) }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-lg font-bold text-gray-900">Edit Account: {{ $account->domain }}</h2>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('accounts.update', $account) }}" class="space-y-5">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                    <input type="text" name="ip_address" value="{{ old('ip_address', $account->ip_address) }}" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Package</label>
                    <select name="package_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}" {{ $account->package_id == $package->id ? 'selected' : '' }}>{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-3 border-t border-gray-200">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-save mr-2"></i> Save Changes</button>
                <a href="{{ route('accounts.show', $account) }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
