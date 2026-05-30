@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Anti-Spam (Spamhaus)</h1>
    <div class="bg-white rounded-lg shadow p-6">
        <p class="mb-4"><strong>Status:</strong> {{ $installed ? 'Installed' : 'Not Installed' }}</p>
        <div class="flex gap-2">
            @if(!$installed)<form method="POST" action="{{ route('antispam.install') }}">@csrf<button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Install</button></form>@endif
            @if($installed)<form method="POST" action="{{ route('antispam.uninstall') }}">@csrf<button type="submit" class="bg-red-600 text-white px-4 py-2 rounded">Uninstall</button></form>@endif
        </div>
    </div>
</div>
@endsection
