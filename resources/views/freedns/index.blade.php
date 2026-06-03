@extends('layouts.app')
@section('title', 'FreeDNS Manager')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-globe mr-2"></i>FreeDNS Manager</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Add FreeDNS Zone</h2>
        <form action="{{ route('freedns.add') }}" method="POST" class="flex gap-4 items-end">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">Domain</label>
                <input type="text" name="domain" placeholder="example.com" class="w-full border rounded-lg px-3 py-2 mt-1" required>
            </div>
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700">IP Address</label>
                <input type="text" name="ip" placeholder="{{ request()->server->get('SERVER_ADDR', '127.0.0.1') }}" class="w-full border rounded-lg px-3 py-2 mt-1" required>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Add Zone</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($zones as $zone)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono text-gray-800">{{ $zone }}</td>
                    <td class="px-6 py-4 text-right text-sm space-x-3">
                        <a href="{{ route('freedns.edit', ['domain' => $zone]) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                        <form action="{{ route('freedns.delete') }}" method="POST" class="inline" onsubmit="return confirm('Delete zone {{ $zone }}?')">@csrf<input type="hidden" name="domain" value="{{ $zone }}"><button type="submit" class="text-red-600 hover:text-red-800">Delete</button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="2" class="px-6 py-4 text-center text-gray-500">No free DNS zones configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
