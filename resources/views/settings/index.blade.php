@extends('layouts.app')
@section('title', 'OpenPanel Settings')
@section('content')
<div class="max-w-2xl space-y-6">
    <!-- Profile -->
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-user mr-2 text-indigo-500"></i>Profile Settings</h3>
        <form method="POST" action="{{ route('settings.update-profile') }}" class="space-y-4">
            @csrf @method('PUT')
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ auth()->user()->email }}" required class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                <select name="language" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                    <option value="en" {{ auth()->user()->language=='en'?'selected':'' }}>English</option>
                    <option value="es" {{ auth()->user()->language=='es'?'selected':'' }}>Spanish</option>
                    <option value="de" {{ auth()->user()->language=='de'?'selected':'' }}>German</option>
                    <option value="fr" {{ auth()->user()->language=='fr'?'selected':'' }}>French</option>
                </select></div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700"><i class="fas fa-save mr-1"></i> Save</button>
        </form>
    </div>
    <!-- Password -->
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-lock mr-2 text-yellow-500"></i>Change Password</h3>
        <form method="POST" action="{{ route('settings.change-password') }}" class="space-y-4">
            @csrf @method('PUT')
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <input type="password" name="current_password" required class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="password" required class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" required class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"></div>
            </div>
            <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700"><i class="fas fa-key mr-1"></i> Change Password</button>
        </form>
    </div>
</div>
@endsection
