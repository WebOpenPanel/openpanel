@extends('layouts.app')
@section('title', 'Edit Account - ' . ($account['domain'] ?? ''))

@section('content')
@php $a = (object) $account; @endphp
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('accounts.show', $a->username) }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-lg font-bold text-gray-900">Edit Account: {{ $a->domain }}</h2>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('accounts.update', $a->username) }}" class="space-y-5">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                    <input type="text" name="ip_address" value="{{ old('ip_address', $a->ip_address) }}" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Package</label>
                    <select name="package" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        @foreach($packages ?? [] as $pkg)
                            <option value="{{ $pkg->name ?? $pkg }}" {{ ($a->package ?? '') == ($pkg->name ?? $pkg) ? 'selected' : '' }}>{{ $pkg->name ?? $pkg }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="border-t border-gray-200 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Change Password</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" name="new_password_confirmation" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-3 border-t border-gray-200">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-save mr-2"></i> Save Changes</button>
                <a href="{{ route('accounts.show', $a->username) }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
