@extends('layouts.app')
@section('title', 'Script Installer')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-box mr-2"></i>Script Installer</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif
    @if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($scripts as $key => $script)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800">{{ $script['name'] }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ $script['description'] }}</p>
            <div x-data="{ open: false }" class="mt-4">
                <button @click="open = !open" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm">Install</button>
                <div x-show="open" x-collapse class="mt-4 space-y-3">
                    <form action="{{ route('scripts.install') }}" method="POST">
                        @csrf
                        <input type="hidden" name="script" value="{{ $key }}">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Domain</label>
                            <input type="text" name="domain" placeholder="example.com" value="{{ old('domain') }}" class="w-full border rounded-lg px-3 py-2 mt-1" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Username</label>
                            <input type="text" name="username" placeholder="username" value="{{ old('username') }}" pattern="[a-z0-9_]+" class="w-full border rounded-lg px-3 py-2 mt-1" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Directory (optional)</label>
                            <input type="text" name="directory" placeholder="subfolder" value="{{ old('directory') }}" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>
                        @if($script['db_required'])
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Database Name</label>
                            <input type="text" name="db_name" placeholder="mydb" value="{{ old('db_name') }}" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">DB User</label>
                            <input type="text" name="db_user" placeholder="myuser" value="{{ old('db_user') }}" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">DB Password</label>
                            <input type="text" name="db_pass" placeholder="auto-generated" value="{{ old('db_pass') }}" class="w-full border rounded-lg px-3 py-2 mt-1">
                        </div>
                        @endif
                        <button type="submit" class="mt-3 w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm" data-script="{{ $script['name'] }}" onclick="return confirm('Install ' + this.dataset.script + '?')">Confirm Install</button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
