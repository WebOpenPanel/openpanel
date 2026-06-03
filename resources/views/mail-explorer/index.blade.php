@extends('layouts.app')
@section('title', 'Mail Explorer')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-envelope-open mr-2"></i>Mail Explorer</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-600 mb-4">Browse mail directories for domains. Select a domain to view its mail files.</p>
        <form action="{{ route('mail-explorer.browse') }}" method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">Domain</label>
                <select name="domain" class="w-full border rounded-lg px-3 py-2 mt-1">
                    @foreach($domains as $domain)
                    <option value="{{ $domain }}">{{ $domain }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700">User (optional)</label>
                <input type="text" name="user" placeholder="username" class="w-full border rounded-lg px-3 py-2 mt-1">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Browse</button>
        </form>
    </div>

    @if(empty($domains))
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800">No domains found in postfix vhost file. Add domains first.</p>
    </div>
    @else
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($domains as $domain)
                <tr>
                    <td class="px-6 py-4 text-sm text-gray-800">{{ $domain }}</td>
                    <td class="px-6 py-4 text-right"><a href="{{ route('mail-explorer.browse', ['domain' => $domain]) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Browse Mail</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
