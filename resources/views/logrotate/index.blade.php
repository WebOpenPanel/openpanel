@extends('layouts.app')
@section('title', 'Logrotate Manager')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-sync mr-2"></i>Logrotate Manager</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Config File</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($configs as $config)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono text-gray-800">{{ $config }}</td>
                    <td class="px-6 py-4 text-right text-sm space-x-3">
                        <a href="{{ route('logrotate.edit', ['name' => $config]) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                        <form action="{{ route('logrotate.test') }}" method="POST" class="inline">@csrf<input type="hidden" name="name" value="{{ $config }}"><button type="submit" class="text-yellow-600 hover:text-yellow-800">Test</button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="2" class="px-6 py-4 text-center text-gray-500">No logrotate configs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
