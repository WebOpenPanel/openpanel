@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Themes & Languages</h1>
    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Theme</h2>
            <form method="POST" action="{{ route('themes.set-theme') }}">@csrf
                <select name="theme" class="w-full border rounded p-2 mb-4">@foreach($themes as $t)<option value="{{ $t }}" {{ $t === $currentTheme ? 'selected' : '' }}>{{ $t }}</option>@endforeach</select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Set Theme</button>
            </form>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Language</h2>
            <form method="POST" action="{{ route('themes.set-language') }}">@csrf
                <select name="language" class="w-full border rounded p-2 mb-4">@foreach($languages as $l)<option value="{{ $l }}" {{ $l === $currentLang ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Set Language</button>
            </form>
        </div>
    </div>
</div>
@endsection