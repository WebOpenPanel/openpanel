@extends('user-layouts.app')

@section('title', 'File Manager')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2 text-sm text-gray-600">
            <a href="{{ route('user.files.index') }}" class="hover:text-indigo-600">Home</a>
            @php $segments = explode('/', trim($path, '/')); $currentPath = ''; @endphp
            @foreach($segments as $segment)
                @if($segment)
                    @php $currentPath .= '/' . $segment; @endphp
                    <span>/</span>
                    <a href="{{ route('user.files.index', ['path' => ltrim($currentPath, '/')]) }}" class="hover:text-indigo-600">{{ $segment }}</a>
                @endif
            @endforeach
        </div>
        <span class="text-xs text-gray-500">Disk: {{ $diskUsed }}</span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Files</h3>
            <form method="POST" action="{{ route('user.files.mkdir') }}" class="flex gap-2">
                @csrf
                <input type="hidden" name="path" value="{{ $path }}">
                <input type="text" name="name" placeholder="New folder name" class="px-3 py-1 border border-gray-300 rounded text-sm" required>
                <button type="submit" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700"><i class="fas fa-folder-plus mr-1"></i>New Folder</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permissions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modified</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @if($path)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3">
                                <a href="{{ route('user.files.index', ['path' => dirname($path) === '.' ? '' : dirname($path)]) }}" class="text-indigo-600 hover:underline text-sm">
                                    <i class="fas fa-arrow-up mr-2"></i>..
                                </a>
                            </td>
                            <td colspan="4"></td>
                        </tr>
                    @endif
                    @foreach($items as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3">
                                @if($item['is_dir'])
                                    <a href="{{ route('user.files.index', ['path' => $item['path']]) }}" class="text-yellow-600 hover:underline text-sm">
                                        <i class="fas fa-folder mr-2"></i>{{ $item['name'] }}
                                    </a>
                                @else
                                    <a href="{{ route('user.files.read', ['path' => $item['path']]) }}" class="text-gray-800 hover:underline text-sm">
                                        <i class="fas fa-file mr-2 text-gray-400"></i>{{ $item['name'] }}
                                    </a>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ $item['is_dir'] ? '-' : number_format($item['size'] / 1024, 1) . ' KB' }}</td>
                            <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ $item['perms'] }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ date('Y-m-d H:i', $item['modified']) }}</td>
                            <td class="px-6 py-3">
                                <form method="POST" action="{{ route('user.files.delete') }}" class="inline" onsubmit="return confirm('Delete ' + this.dataset.name + '?')" data-name="{{ $item['name'] }}">
                                    @csrf
                                    <input type="hidden" name="path" value="{{ $item['path'] }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    @if(empty($items))
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Empty directory.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
