@extends('layouts.app')
@section('title', 'Snuffleupagus')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Snuffleupagus PHP Security</h1>
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-6">Snuffleupagus is not installed.</div>
    <form method="POST" action="{{ route('snuffleupagus.install') }}">@csrf<button class="bg-green-600 text-white px-6 py-2 rounded">Install Snuffleupagus</button></form>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Security Settings</h2>
            <form method="POST" action="{{ route('snuffleupagus.config') }}">@csrf
                @foreach(['cookie_encrypt' => 'Encrypt Cookies', 'disable_xxe' => 'Disable XXE', 'disable_eval' => 'Disable eval()', 'disable_exec' => 'Disable exec/system', 'global_strict' => 'Global Strict Mode', 'allow_broken' => 'Allow Broken Config'] as $key => $label)
                <label class="flex items-center gap-2 py-2"><input type="checkbox" name="{{ $key }}" value="1" {{ ($config[$key] ?? false) ? 'checked' : '' }}><span>{{ $label }}</span></label>
                @endforeach
                <label class="block text-sm font-medium mt-4 mb-1">Custom Rules</label>
                <textarea name="custom_rules" rows="6" class="w-full border rounded p-2 font-mono text-sm">{{ $config['custom_rules'] ?? '' }}</textarea>
                <button class="mt-3 bg-blue-600 text-white px-4 py-2 rounded">Save Configuration</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Active Rules File</h2>
            <form method="POST" action="{{ route('snuffleupagus.rules') }}">@csrf
                <textarea name="rules" rows="20" class="w-full border rounded p-2 font-mono text-sm">{{ $rules }}</textarea>
                <button class="mt-3 bg-blue-600 text-white px-4 py-2 rounded">Save Rules</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
