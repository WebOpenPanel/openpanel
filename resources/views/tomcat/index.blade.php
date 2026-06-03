@extends('layouts.app')
@section('title', 'Tomcat Manager')
@section('content')
<div class="space-y-6">
    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800">Tomcat is not installed.</p>
        <form method="POST" action="{{ route('tomcat.install') }}" class="mt-2">@csrf
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Install Tomcat</button>
        </form>
    </div>
    @else
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Tomcat {{ $version }}</h2>
        <div class="flex gap-2">
            @foreach(['start','stop','restart'] as $act)
            <form method="POST" action="{{ route('tomcat.service') }}">@csrf
                <input type="hidden" name="action" value="{{ $act }}">
                <button class="bg-{{ $act==='stop'?'red':'green' }}-600 text-white px-3 py-1 rounded text-sm">{{ ucfirst($act) }}</button>
            </form>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Deploy WAR</h3>
        <form method="POST" action="{{ route('tomcat.deploy') }}" enctype="multipart/form-data" class="flex gap-2">
            @csrf
            <input type="file" name="war" class="border rounded px-3 py-1" required>
            <input name="context" placeholder="Context path" class="border rounded px-3 py-1 w-40" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Deploy</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Deployed Apps</h3>
        @foreach($apps as $app)
        <div class="flex items-center justify-between border-b py-2">
            <span class="text-sm">/{{ $app }}</span>
            <form method="POST" action="{{ route('tomcat.undeploy') }}">@csrf
                <input type="hidden" name="context" value="{{ $app }}">
                <button class="text-red-600 text-sm">Undeploy</button>
            </form>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
