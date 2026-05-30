@extends('layouts.app')
@section('title', 'Services')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('server.services') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Services</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Service</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Boot</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($services as $svc)
                @php $name = $svc->Service ?? $svc->service ?? ''; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-sm font-medium text-gray-800">{{ $name }}</td>
                    <td class="px-4 py-2.5">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ ($svc->Active ?? '') === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $svc->Active ?? 'unknown' }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-sm text-gray-500">{{ $svc->Loaded ?? '' }}</td>
                    <td class="px-4 py-2.5">
                        <form method="POST" action="{{ route('server.service-action', ['action' => 'restart', 'service' => $name]) }}" class="inline">@csrf<button class="px-2 py-1 bg-yellow-600 text-white rounded text-xs hover:bg-yellow-700 mr-1">Restart</button></form>
                        <form method="POST" action="{{ route('server.service-action', ['action' => 'stop', 'service' => $name]) }}" class="inline">@csrf<button class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">Stop</button></form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
