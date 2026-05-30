@extends('layouts.app')
@section('title', 'Services')
@section('content')
<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($services as $service)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-800">{{ $service->display_name }}</h3>
                @if($service->isRunning())
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <i class="fas fa-circle text-green-400 mr-1 text-[5px]"></i> Running
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        <i class="fas fa-circle text-red-400 mr-1 text-[5px]"></i> Stopped
                    </span>
                @endif
            </div>
            <div class="text-xs text-gray-500 space-y-1 mb-3">
                <p>Service: {{ $service->service_name ?? $service->name }}</p>
                <p>Type: {{ $service->type }}</p>
                @if($service->last_restarted_at)<p>Last Restart: {{ $service->last_restarted_at->diffForHumans() }}</p>@endif
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('services.restart', $service) }}" class="inline">@csrf
                    <button class="px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded-lg text-xs hover:bg-yellow-200"><i class="fas fa-redo mr-1"></i>Restart</button>
                </form>
                <form method="POST" action="{{ route('services.toggle', $service) }}" class="inline">@csrf
                    @if($service->isRunning())
                        <button class="px-3 py-1.5 bg-red-100 text-red-700 rounded-lg text-xs hover:bg-red-200"><i class="fas fa-stop mr-1"></i>Stop</button>
                    @else
                        <button class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg text-xs hover:bg-green-200"><i class="fas fa-play mr-1"></i>Start</button>
                    @endif
                </form>
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white rounded-xl border p-12 text-center">
            <i class="fas fa-cogs text-gray-300 text-3xl mb-3"></i>
            <p class="text-sm text-gray-500">No services configured yet.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
