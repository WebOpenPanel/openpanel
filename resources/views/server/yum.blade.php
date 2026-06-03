@extends('layouts.app')
@section('title', 'Yum Packages')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('server.yum') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Packages</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-box mr-2 text-indigo-500"></i>Package Manager</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <form method="POST" action="{{ route('server.yum-install') }}" class="flex gap-2">@csrf
                <input type="text" name="package" placeholder="Package name" class="flex-1 px-3 py-2 border rounded-lg text-sm" required>
                <button class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">Install</button>
            </form>
            <form method="POST" action="{{ route('server.yum-update') }}" class="flex gap-2">@csrf
                <input type="text" name="package" placeholder="Package (blank = all)" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                <button class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Update</button>
            </form>
        </div>
        @if(session('output'))
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-48 font-mono mb-4">{{ session('output') }}</pre>
        @endif
        <form method="GET" action="{{ route('server.yum') }}" class="flex gap-2 mb-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search packages..." class="flex-1 px-3 py-2 border rounded-lg text-sm">
            <button class="px-3 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700">Search</button>
        </form>
        @if(!empty($packages))
        <div class="max-h-64 overflow-auto"><table class="w-full text-xs"><tbody class="divide-y">
            @foreach($packages as $pkg)
            <tr class="hover:bg-gray-50">
                <td class="py-1.5 px-2 font-mono">{{ is_array($pkg) ? ($pkg['name'] ?? $pkg) : $pkg }}</td>
                @if(is_array($pkg))
                <td class="py-1.5 px-2 text-gray-500">{{ $pkg['version'] ?? '' }}</td>
                <td class="py-1.5 px-2 text-gray-400">{{ $pkg['repo'] ?? '' }}</td>
                @endif
            </tr>
            @endforeach
        </tbody></table></div>
        @endif
    </div>
</div>
@endsection
