@extends('layouts.app')
@section('title', 'PHP.ini Editor')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Quick Settings</h3>
        <form method="POST" action="{{ route('phpini.update') }}" class="flex gap-2">
            @csrf
            <select name="key" class="border rounded px-3 py-1">
                <option value="memory_limit">memory_limit</option>
                <option value="max_execution_time">max_execution_time</option>
                <option value="upload_max_filesize">upload_max_filesize</option>
                <option value="post_max_size">post_max_size</option>
                <option value="max_input_time">max_input_time</option>
                <option value="date.timezone">date.timezone</option>
            </select>
            <input name="value" placeholder="Value" class="border rounded px-3 py-1 flex-1" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Update</button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Full php.ini ({{ $iniPath }})</h3>
        <form method="POST" action="{{ route('phpini.save') }}">
            @csrf
            <textarea name="config" rows="30" class="w-full font-mono text-sm border rounded p-3">{{ $config }}</textarea>
            <button class="mt-2 bg-blue-600 text-white px-4 py-2 rounded">Save & Restart PHP-FPM</button>
        </form>
    </div>
</div>
@endsection
