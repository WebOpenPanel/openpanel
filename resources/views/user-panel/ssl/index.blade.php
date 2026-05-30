@extends('user-layouts.app')

@section('title', 'SSL Certificates')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800">SSL Certificates</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($domains as $domain)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ $domain->domain }}</td>
                        <td class="px-6 py-4">
                            @if($certs[$domain->domain]['exists'])
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">Active</span>
                            @else
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-500 rounded-full">None</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $certs[$domain->domain]['expires'] ?? '-' }}</td>
                        <td class="px-6 py-4 space-x-2">
                            <form method="POST" action="{{ route('user.ssl.request') }}" class="inline">
                                @csrf
                                <input type="hidden" name="domain" value="{{ $domain->domain }}">
                                <button type="submit" class="text-green-600 hover:text-green-800 text-sm"><i class="fas fa-lock mr-1"></i>Let's Encrypt</button>
                            </form>
                            <form method="POST" action="{{ route('user.ssl.selfsigned') }}" class="inline">
                                @csrf
                                <input type="hidden" name="domain" value="{{ $domain->domain }}">
                                <button type="submit" class="text-blue-600 hover:text-blue-800 text-sm"><i class="fas fa-certificate mr-1"></i>Self-Signed</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
