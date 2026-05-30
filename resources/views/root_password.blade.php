@extends('layouts.app')
@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Change Root Password</h1>
    <div class="bg-white rounded-lg shadow p-6 max-w-lg">
        <form method="POST" action="{{ route('root-password-change') }}">@csrf
            <div class="mb-4"><label class="block text-sm font-medium">New Password</label><input type="password" name="password" class="mt-1 block w-full border rounded p-2" required></div>
            <div class="mb-4"><label class="block text-sm font-medium">Confirm Password</label><input type="password" name="password_confirmation" class="mt-1 block w-full border rounded p-2" required></div>
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded">Change Password</button>
        </form>
    </div>
</div>
@endsection