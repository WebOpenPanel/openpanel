@extends('layouts.app')
@section('title', 'Add DNS Zone')
@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" action="{{ route('dns.store') }}" class="space-y-5">
            @csrf
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Domain Name</label>
                <input type="text" name="domain" required placeholder="example.com" class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Nameserver 1</label>
                    <input type="text" name="nameserver1" placeholder="ns1.example.com" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Nameserver 2</label>
                    <input type="text" name="nameserver2" placeholder="ns2.example.com" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">NS1 IP</label>
                    <input type="text" name="nameserver1_ip" placeholder="1.2.3.4" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">NS2 IP</label>
                    <input type="text" name="nameserver2_ip" placeholder="5.6.7.8" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                </div>
            </div>
            <div class="flex items-center gap-3 pt-3 border-t">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-plus mr-2"></i> Create Zone</button>
                <a href="{{ route('dns.index') }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
