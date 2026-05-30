@extends('layouts.app')
@section('title', 'SSL Certificates')
@section('content')
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <div class="flex gap-2">
            <a href="{{ route('ssl.index') }}" class="px-3 py-1.5 text-sm bg-indigo-100 text-indigo-700 rounded-lg">All</a>
            <a href="{{ route('ssl.index', ['status' => 'active']) }}" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Active</a>
            <a href="{{ route('ssl.index', ['status' => 'expired']) }}" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Expired</a>
        </div>
        <a href="{{ route('ssl.generate') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-plus mr-2"></i> Generate SSL</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Domain</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Expires</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($certificates as $cert)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm font-medium text-gray-800">{{ $cert->domain }}</td>
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 text-xs bg-gray-100 rounded-full">{{ $cert->type }}</span></td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">
                        @if($cert->expires_at)
                            {{ $cert->expires_at->format('Y-m-d') }}
                            @if($cert->isExpired())<span class="text-red-500 ml-1 text-xs">(Expired)</span>
                            @elseif($cert->daysUntilExpiry() < 30)<span class="text-yellow-500 ml-1 text-xs">({{ $cert->daysUntilExpiry() }}d)</span>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 text-xs {{ $cert->status=='active'?'bg-green-100 text-green-800':'bg-red-100 text-red-800' }} rounded-full">{{ $cert->status }}</span></td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('ssl.destroy', $cert) }}" onsubmit="return confirm('Delete?')" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">No SSL certificates.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $certificates->links() }}</div>
</div>
@endsection
