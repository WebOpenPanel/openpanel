@extends('layouts.app')
@section('title', 'Mass Email')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-mail-bulk mr-2"></i>Mass Email</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-600 mb-4">Send notification emails to all users, resellers only, or regular users only. Emails are sent to username@hostname.</p>
        <form action="{{ route('mass-email.send') }}" method="POST" onsubmit="return confirm('Send this email to all selected users?')">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Recipients</label>
                    <select name="recipients" class="w-full border rounded-lg px-3 py-2 mt-1">
                        <option value="all">All Users ({{ count($users) }})</option>
                        <option value="resellers">Resellers Only</option>
                        <option value="users">Regular Users Only</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Subject</label>
                    <input type="text" name="subject" maxlength="200" class="w-full border rounded-lg px-3 py-2 mt-1" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea name="message" rows="8" maxlength="10000" class="w-full border rounded-lg px-3 py-2 mt-1" required></textarea>
                </div>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Send Email</button>
            </div>
        </form>
    </div>
</div>
@endsection
