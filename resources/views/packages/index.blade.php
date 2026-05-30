@extends('layouts.app')
@section('title', 'Packages')
@section('content')
<div class="space-y-4">
    <div class="flex justify-end">
        <a href="{{ route('packages.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-plus mr-2"></i> New Package</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($packages as $package)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
            <div class="px-5 py-4 bg-gradient-to-r from-indigo-500 to-purple-600">
                <h3 class="text-white font-bold text-lg">{{ $package->name }}</h3>
                <p class="text-indigo-100 text-xs mt-0.5">{{ $package->description ?? 'Hosting Package' }}</p>
            </div>
            <div class="p-5 space-y-2.5">
                <div class="flex justify-between text-sm"><span class="text-gray-500">Disk Space</span><span class="font-medium text-gray-800">{{ $package->disk_space_formatted }}</span></div>
                <div class="flex justify-between text-sm"><span class="text-gray-500">Bandwidth</span><span class="font-medium text-gray-800">{{ $package->bandwidth_formatted }}</span></div>
                <div class="flex justify-between text-sm"><span class="text-gray-500">Domains</span><span class="font-medium text-gray-800">{{ $package->max_domains }}</span></div>
                <div class="flex justify-between text-sm"><span class="text-gray-500">Emails</span><span class="font-medium text-gray-800">{{ $package->max_email_accounts }}</span></div>
                <div class="flex justify-between text-sm"><span class="text-gray-500">Databases</span><span class="font-medium text-gray-800">{{ $package->max_databases }}</span></div>
                <div class="flex justify-between text-sm"><span class="text-gray-500">Shell</span><span class="font-medium text-gray-800">{{ $package->shell_access ? 'Yes' : 'No' }}</span></div>
                <div class="flex justify-between text-sm"><span class="text-gray-500">Accounts</span><span class="font-medium text-gray-800">{{ $package->user_accounts_count }}</span></div>
            </div>
            <div class="px-5 py-3 bg-gray-50 border-t flex justify-between">
                <a href="{{ route('packages.edit', $package) }}" class="text-sm text-indigo-600 hover:text-indigo-800"><i class="fas fa-edit mr-1"></i>Edit</a>
                <form method="POST" action="{{ route('packages.destroy', $package) }}" onsubmit="return confirm('Delete package?')">@csrf @method('DELETE')
                    <button class="text-sm text-red-600 hover:text-red-800"><i class="fas fa-trash mr-1"></i>Delete</button>
                </form>
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white rounded-xl border p-12 text-center">
            <i class="fas fa-box text-gray-300 text-3xl mb-3"></i>
            <p class="text-sm text-gray-500">No packages created yet.</p>
            <a href="{{ route('packages.create') }}" class="mt-2 inline-flex items-center text-sm text-indigo-600"><i class="fas fa-plus mr-1"></i>Create your first package</a>
        </div>
        @endforelse
    </div>
</div>
@endsection
