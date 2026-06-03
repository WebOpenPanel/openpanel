@extends('layouts.app')
@section('title', 'Startup Services')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-power-off mr-2"></i>Startup Services</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Boot State</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($services as $svc)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono text-gray-800">{{ $svc['name'] }}</td>
                    <td class="px-6 py-4 text-sm"><span class="px-2 py-1 text-xs rounded {{ $svc['enabled'] === 'enabled' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ $svc['enabled'] }}</span></td>
                    <td class="px-6 py-4 text-sm"><span class="px-2 py-1 text-xs rounded {{ $svc['active'] === 'active' ? 'bg-green-100 text-green-800' : ($svc['active'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">{{ $svc['active'] }}</span></td>
                    <td class="px-6 py-4 text-right text-sm space-x-2">
                        @if($svc['enabled'] === 'enabled')
                        <form action="{{ route('startup-services.toggle') }}" method="POST" class="inline">@csrf<input type="hidden" name="service" value="{{ $svc['name'] }}"><input type="hidden" name="action" value="disable"><button type="submit" class="text-yellow-600 hover:text-yellow-800" title="Disable at boot">Disable</button></form>
                        @else
                        <form action="{{ route('startup-services.toggle') }}" method="POST" class="inline">@csrf<input type="hidden" name="service" value="{{ $svc['name'] }}"><input type="hidden" name="action" value="enable"><button type="submit" class="text-green-600 hover:text-green-800" title="Enable at boot">Enable</button></form>
                        @endif
                        @if($svc['active'] === 'active')
                        <form action="{{ route('startup-services.toggle') }}" method="POST" class="inline">@csrf<input type="hidden" name="service" value="{{ $svc['name'] }}"><input type="hidden" name="action" value="stop"><button type="submit" class="text-red-600 hover:text-red-800">Stop</button></form>
                        <form action="{{ route('startup-services.toggle') }}" method="POST" class="inline">@csrf<input type="hidden" name="service" value="{{ $svc['name'] }}"><input type="hidden" name="action" value="restart"><button type="submit" class="text-blue-600 hover:text-blue-800">Restart</button></form>
                        @else
                        <form action="{{ route('startup-services.toggle') }}" method="POST" class="inline">@csrf<input type="hidden" name="service" value="{{ $svc['name'] }}"><input type="hidden" name="action" value="start"><button type="submit" class="text-green-600 hover:text-green-800">Start</button></form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
