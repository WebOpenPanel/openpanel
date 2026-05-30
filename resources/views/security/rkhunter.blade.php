@extends('layouts.app')
@section('title', 'RKHunter')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('security.maldet') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Maldet</a>
        <a href="{{ route('security.rkhunter') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">RKHunter</a>
        <a href="{{ route('security.lynis') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Lynis</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-search mr-2 text-orange-500"></i>RKHunter</h3>
        @if($installed)
        <form method="POST" action="{{ route('security.rkhunter-action') }}" class="flex flex-wrap gap-2 mb-4">
            @csrf
            <button name="action" value="update" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700">Update</button>
            <button name="action" value="scan" class="px-3 py-1.5 bg-yellow-600 text-white rounded-lg text-xs hover:bg-yellow-700">Scan Now</button>
            <button name="action" value="uninstall" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700" onclick="return confirm('Uninstall?')">Uninstall</button>
        </form>
        @if(!empty($scans))
        <div class="space-y-3">
            @foreach(array_slice($scans, 0, 5) as $scan)
            <details class="border rounded-lg">
                <summary class="px-4 py-2.5 text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-50"><i class="fas fa-file-alt mr-1 text-indigo-500"></i>{{ $scan['file'] }} <span class="text-gray-400 font-normal ml-2">{{ $scan['time'] }}</span></summary>
                <pre class="px-4 py-3 text-xs bg-gray-50 overflow-auto max-h-48 font-mono">{{ $scan['content'] }}</pre>
            </details>
            @endforeach
        </div>
        @endif
        @else
        <form method="POST" action="{{ route('security.rkhunter-action') }}">@csrf<input type="hidden" name="action" value="install"><button class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">Install RKHunter</button></form>
        @endif
    </div>
    @if(session('output'))
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <pre class="bg-gray-900 text-green-400 p-3 rounded text-xs overflow-auto max-h-64 font-mono">{{ session('output') }}</pre>
    </div>
    @endif
</div>
@endsection
