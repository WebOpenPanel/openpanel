@extends('layouts.app')
@section('title', 'Mail Explorer')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2 flex-wrap">
        <a href="{{ route('email.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Accounts</a>
        <a href="{{ route('email.explorer') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Explorer</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-2.5 bg-gray-50 border-b text-sm text-gray-600"><i class="fas fa-folder mr-1"></i>{{ $directory }}</div>
        <table class="w-full">
            <thead class="border-b"><tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Size</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Modified</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm">
                        @if($item['is_dir'])
                            <a href="{{ route('email.explorer', ['path' => $directory . '/' . $item['name']]) }}" class="text-indigo-600 hover:underline"><i class="fas fa-folder mr-1 text-yellow-500"></i>{{ $item['name'] }}</a>
                        @else
                            <i class="fas fa-file mr-1 text-gray-400"></i>{{ $item['name'] }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ $item['is_dir'] ? 'Directory' : 'File' }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ $item['is_dir'] ? '-' : number_format($item['size'] / 1024, 1) . ' KB' }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ date('Y-m-d H:i', $item['modified']) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Empty directory</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
