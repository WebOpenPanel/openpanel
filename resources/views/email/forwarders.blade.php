@extends('layouts.app')
@section('title', 'Email Forwarders')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('email.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Accounts</a>
        <a href="{{ route('email.forwarders') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Forwarders</a>
        <a href="{{ route('email.autoresponders') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Autoresponders</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-share mr-2 text-blue-500"></i>Add Forwarder</h3>
        <form method="POST" action="{{ route('email.create-forwarder') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <select name="user_account_id" required class="px-3 py-2 border rounded-lg text-sm"><option value="">Account</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->domain }}</option>@endforeach</select>
            <input type="text" name="source_prefix" required placeholder="Source prefix" class="px-3 py-2 border rounded-lg text-sm w-36">
            <input type="email" name="destination_email" required placeholder="dest@example.com" class="px-3 py-2 border rounded-lg text-sm w-56">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Add</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Source</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Destination</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($forwarders as $f)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm text-gray-800">{{ $f->source_email }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $f->destination_email }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('email.destroy-forwarder', $f) }}" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">No forwarders.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $forwarders->links() }}</div>
</div>
@endsection
