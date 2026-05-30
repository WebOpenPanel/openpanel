@extends('layouts.app')
@section('title', 'Email Autoresponders')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('email.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Accounts</a>
        <a href="{{ route('email.forwarders') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Forwarders</a>
        <a href="{{ route('email.autoresponders') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Autoresponders</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-reply mr-2 text-purple-500"></i>Add Autoresponder</h3>
        <form method="POST" action="{{ route('email.create-autoresponder') }}" class="space-y-3">
            @csrf
            <div class="flex gap-3">
                <select name="user_account_id" required class="px-3 py-2 border rounded-lg text-sm"><option value="">Account</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->domain }}</option>@endforeach</select>
                <input type="text" name="email_prefix" required placeholder="Email prefix" class="px-3 py-2 border rounded-lg text-sm w-40">
                <input type="text" name="subject" required placeholder="Subject" class="flex-1 px-3 py-2 border rounded-lg text-sm">
            </div>
            <textarea name="body" required rows="3" placeholder="Auto-reply message..." class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700">Create</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Subject</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($autoresponders as $ar)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm text-gray-800">{{ $ar->email }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $ar->subject }}</td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('email.destroy-autoresponder', $ar) }}" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">No autoresponders.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $autoresponders->links() }}</div>
</div>
@endsection
