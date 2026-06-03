@extends('layouts.app')
@section('title', 'Disk Quotas')
@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">Set User Quota</h3>
        <form method="POST" action="{{ route('disk-quota.set') }}" class="flex gap-2">
            @csrf
            <input name="username" placeholder="Username" class="border rounded px-3 py-1" required>
            <input name="soft_mb" placeholder="Soft (MB)" class="border rounded px-3 py-1 w-32" required>
            <input name="hard_mb" placeholder="Hard (MB)" class="border rounded px-3 py-1 w-32" required>
            <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Set</button>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold mb-3">User Quotas</h3>
        <table class="w-full text-sm">
            <thead><tr class="border-b"><th class="text-left py-2">User</th><th>Used</th><th>Soft</th><th>Hard</th><th>Action</th></tr></thead>
            <tbody>
            @foreach($quotas as $q)
            <tr class="border-b">
                <td class="py-2">{{ $q['user'] }}</td><td>{{ $q['used'] }}</td><td>{{ $q['soft'] }}</td><td>{{ $q['hard'] }}</td>
                <td>
                    <form method="POST" action="{{ route('disk-quota.remove') }}" class="inline">@csrf
                        <input type="hidden" name="username" value="{{ $q['user'] }}">
                        <button class="text-red-600 text-sm">Remove</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
