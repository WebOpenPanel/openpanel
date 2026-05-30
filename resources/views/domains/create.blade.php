@extends('layouts.app')
@section('title', 'Add Domain')
@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('domains.store') }}" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User Account</label>
                <select name="user_account_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select Account</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('user_account_id')==$account->id?'selected':'' }}>{{ $account->domain }} ({{ $account->user->username ?? '-' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Domain Name</label>
                <input type="text" name="domain" value="{{ old('domain') }}" required placeholder="example.com" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm">
                    <option value="addon">Addon Domain</option>
                    <option value="parked">Parked Domain</option>
                    <option value="subdomain">Subdomain</option>
                </select>
            </div>
            <div class="flex items-center gap-3 pt-3 border-t">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-plus mr-2"></i> Add Domain</button>
                <a href="{{ route('domains.index') }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
