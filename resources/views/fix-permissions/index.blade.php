@extends('layouts.app')
@section('title', 'Fix Permissions')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-lock mr-2"></i>Fix Permissions</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif
    @if(session('warning'))<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded whitespace-pre-wrap">{!! nl2br(e(session('warning'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-600 mb-4">Fix file ownership and permissions for user public_html directories. Sets directories to 755, files to 644, and corrects ownership.</p>
        <form action="{{ route('fix-permissions.fix-all') }}" method="POST" onsubmit="return confirm('Fix permissions for ALL users? This may take a while.')">
            @csrf
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Fix All Users</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($users as $user)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono text-gray-800">{{ $user }}</td>
                    <td class="px-6 py-4 text-right">
                        <form action="{{ route('fix-permissions.fix') }}" method="POST">@csrf<input type="hidden" name="username" value="{{ $user }}"><button type="submit" class="text-indigo-600 hover:text-indigo-800 text-sm">Fix Permissions</button></form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
