@extends('layouts.app')
@section('title', 'File Manager')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('files.index') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Browse</a>
        <a href="{{ route('files.disk-usage') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Disk Usage</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="flex items-center gap-2 mb-3 flex-wrap">
            <a href="{{ route('files.index', ['path' => '/']) }}" class="text-sm text-indigo-600 hover:underline">/</a>
            @foreach($breadcrumbs as $bc)
            <span class="text-gray-400">/</span>
            <a href="{{ route('files.index', ['path' => $bc['path']]) }}" class="text-sm text-indigo-600 hover:underline">{{ $bc['name'] }}</a>
            @endforeach
        </div>
        <div class="flex gap-2 mb-3">
            <form method="POST" action="{{ route('files.mkdir') }}" class="flex gap-2">@csrf
                <input type="hidden" name="path" value="{{ $path }}">
                <input type="text" name="name" placeholder="New folder" class="px-3 py-1.5 border rounded-lg text-sm" required>
                <button class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">Create Folder</button>
            </form>
            <form method="POST" action="{{ route('files.upload') }}" enctype="multipart/form-data" class="flex gap-2">@csrf
                <input type="hidden" name="path" value="{{ $path }}">
                <input type="file" name="file" class="text-sm" required>
                <button class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700">Upload</button>
            </form>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Size</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Perms</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Owner</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Modified</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @if($path !== '/')
                <tr class="hover:bg-gray-50"><td colspan="6" class="px-4 py-2"><a href="{{ route('files.index', ['path' => dirname($path)]) }}" class="text-indigo-600 text-sm hover:underline"><i class="fas fa-level-up-alt mr-1"></i>..</a></td></tr>
                @endif
                @foreach($items as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm">
                        @if($item['is_dir'])
                            <a href="{{ route('files.index', ['path' => $item['path']]) }}" class="text-indigo-600 hover:underline"><i class="fas fa-folder mr-1 text-yellow-500"></i>{{ $item['name'] }}</a>
                        @else
                            <i class="fas fa-file mr-1 text-gray-400"></i>{{ $item['name'] }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ $item['is_dir'] ? '-' : number_format($item['size'] / 1024, 1) . ' KB' }}</td>
                    <td class="px-4 py-2 text-sm font-mono text-gray-500">{{ $item['permissions'] }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ $item['owner'] }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500">{{ $item['modified'] }}</td>
                    <td class="px-4 py-2">
                        <div class="flex gap-1">
                            @if($item['is_file'])
                            <a href="{{ route('files.edit', ['path' => $item['path']]) }}" class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200">Edit</a>
                            <a href="{{ route('files.download', ['path' => $item['path']]) }}" class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200">Download</a>
                            @endif
                            <form method="POST" action="{{ route('files.delete') }}" class="inline" onsubmit="return confirm('Delete?')">@csrf<input type="hidden" name="path" value="{{ $item['path'] }}"><button class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">Delete</button></form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
