@extends('layouts.app')
@section('title', 'Email Accounts')
@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('email.index') }}" class="px-3 py-1.5 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-lg">Accounts</a>
        <a href="{{ route('email.forwarders') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Forwarders</a>
        <a href="{{ route('email.autoresponders') }}" class="px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">Autoresponders</a>
    </div>
    <!-- Create Account -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-envelope mr-2 text-green-500"></i>Create Email Account</h3>
        <form method="POST" action="{{ route('email.create-account') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div><label class="text-xs text-gray-500">Account</label>
                <select name="user_account_id" required class="block px-3 py-2 border rounded-lg text-sm w-48">
                    <option value="">Select</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->domain }}</option>@endforeach
                </select></div>
            <div><label class="text-xs text-gray-500">Email Prefix</label>
                <input type="text" name="email_prefix" required placeholder="info" class="block px-3 py-2 border rounded-lg text-sm w-32"></div>
            <div><label class="text-xs text-gray-500">Password</label>
                <input type="password" name="password" required class="block px-3 py-2 border rounded-lg text-sm w-36"></div>
            <div><label class="text-xs text-gray-500">Confirm</label>
                <input type="password" name="password_confirmation" required class="block px-3 py-2 border rounded-lg text-sm w-36"></div>
            <div><label class="text-xs text-gray-500">Quota (MB)</label>
                <input type="number" name="quota_mb" value="250" class="block px-3 py-2 border rounded-lg text-sm w-24"></div>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">Create</button>
        </form>
    </div>
    <!-- List -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Account</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Quota</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($emailAccounts as $ea)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm font-medium text-gray-800">{{ $ea->email }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $ea->userAccount->domain ?? '-' }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $ea->quota_mb }} MB</td>
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 text-xs {{ $ea->status=='active'?'bg-green-100 text-green-800':'bg-red-100 text-red-800' }} rounded-full">{{ $ea->status }}</span></td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('email.destroy-account', $ea) }}" onsubmit="return confirm('Delete?')" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">No email accounts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $emailAccounts->links() }}</div>
</div>
@endsection
