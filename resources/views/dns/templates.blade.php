@extends('layouts.app')
@section('title', 'DNS Zone Templates')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('dns.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">DNS Zones</a>
        <a href="{{ route('dns.nameservers') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Nameservers</a>
        <a href="{{ route('dns.templates') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Templates</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Template</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Preview</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($templates as $tpl)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-sm font-medium text-gray-800">{{ $tpl['name'] }}</td>
                    <td class="px-4 py-2.5"><pre class="text-xs text-gray-600 bg-gray-50 p-2 rounded max-h-32 overflow-auto">{{ Str::limit($tpl['content'], 300) }}</pre></td>
                </tr>
                @empty
                <tr><td colspan="2" class="px-4 py-8 text-center text-gray-400">No templates found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
